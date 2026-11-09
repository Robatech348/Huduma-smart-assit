(function () {
  'use strict';

  // Mobile nav toggle
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('mainnav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Confirm dangerous actions
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Auto-submit selects (filters, service/centre pickers)
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // ---- SmartAssist chat ----
  var chat = document.getElementById('chat');
  if (!chat) return;

  var log = document.getElementById('chat-log');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-input');
  var endpoint = chat.getAttribute('data-endpoint');
  var feedbackEndpoint = chat.getAttribute('data-feedback');
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var history = [];
  try { history = JSON.parse(sessionStorage.getItem('hsa-chat') || '[]'); } catch (err) { history = []; }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Minimal, safe markdown: escape first, then bold, lists and paragraphs.
  function render(md) {
    var lines = esc(md).split(/\r?\n/);
    var html = '', inList = false, para = [];
    function flushPara() { if (para.length) { html += '<p>' + para.join('<br>') + '</p>'; para = []; } }
    lines.forEach(function (line) {
      var t = line.trim();
      var m = t.match(/^(?:[-*•]|\d+[.)])\s+(.*)$/);
      if (m) {
        flushPara();
        if (!inList) { html += '<ul>'; inList = true; }
        html += '<li>' + m[1] + '</li>';
      } else {
        if (inList) { html += '</ul>'; inList = false; }
        if (t === '') flushPara(); else para.push(t.replace(/^#+\s*/, ''));
      }
    });
    if (inList) html += '</ul>';
    flushPara();
    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }

  function add(role, text, extra) {
    var div = document.createElement('div');
    div.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = role === 'user' ? esc(text) : render(text);
    if (extra && extra.actions && extra.actions.length) {
      var a = document.createElement('div');
      a.className = 'msg-actions';
      extra.actions.forEach(function (act) {
        var link = document.createElement('a');
        link.className = 'btn btn-sm';
        link.href = act.url;
        link.textContent = act.label;
        a.appendChild(link);
      });
      div.appendChild(a);
    }
    if (extra && extra.source) {
      var s = document.createElement('span');
      s.className = 'source';
      s.textContent = extra.source === 'ai'
        ? 'AI-assisted answer based on approved service information. Check important details with staff.'
        : 'Answer from the approved service catalogue.';
      div.appendChild(s);
    }
    if (extra && extra.log_id) {
      var fb = document.createElement('div');
      fb.className = 'fb';
      fb.innerHTML = 'Was this helpful? <button type="button" data-v="1">Yes</button><button type="button" data-v="0">No</button>';
      fb.addEventListener('click', function (e) {
        var v = e.target.getAttribute('data-v');
        if (v === null) return;
        var body = new FormData();
        body.append('id', extra.log_id); body.append('helpful', v); body.append('_csrf', csrf);
        fetch(feedbackEndpoint, { method: 'POST', body: body, credentials: 'same-origin' });
        fb.textContent = 'Thank you for the feedback.';
      });
      div.appendChild(fb);
    }
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
    return div;
  }

  history.forEach(function (h) { add(h.role, h.content); });

  function ask(question) {
    question = question.trim();
    if (!question) return;
    add('user', question);
    input.value = '';
    input.disabled = true;
    var typing = add('bot', 'Looking that up…');
    typing.classList.add('typing');

    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ question: question, history: history.slice(-10) })
    }).then(function (r) { return r.json(); }).then(function (data) {
      typing.remove();
      if (data.error) { add('bot', data.error); return; }
      add('bot', data.answer, data);
      history.push({ role: 'user', content: question });
      history.push({ role: 'assistant', content: data.answer });
      history = history.slice(-20);
      try { sessionStorage.setItem('hsa-chat', JSON.stringify(history)); } catch (err) { /* ignore */ }
    }).catch(function () {
      typing.remove();
      add('bot', 'Sorry, something went wrong. Please try again, or browse the services list.');
    }).finally(function () {
      input.disabled = false;
      input.focus();
    });
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
  document.querySelectorAll('[data-ask]').forEach(function (b) {
    b.addEventListener('click', function () { ask(b.getAttribute('data-ask')); });
  });
  var reset = document.getElementById('chat-reset');
  if (reset) reset.addEventListener('click', function () {
    history = []; try { sessionStorage.removeItem('hsa-chat'); } catch (err) { /* ignore */ }
    log.innerHTML = '';
    add('bot', 'Hello! Ask me about a government service, what documents to bring, fees, or how to book a visit. You can write in English or Kiswahili.');
  });
  if (!history.length) {
    add('bot', 'Hello! Ask me about a government service, what documents to bring, fees, or how to book a visit. You can write in English or Kiswahili.');
  }
  var initial = chat.getAttribute('data-initial');
  if (initial) ask(initial);
})();
