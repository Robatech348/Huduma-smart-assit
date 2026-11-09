const path = require('path');
const PptxGenJS = require(path.join(process.env.TEMP, 'huduma-smartassist-pptx-tooling', 'node_modules', 'pptxgenjs'));

const pptx = new PptxGenJS();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Huduma SmartAssist project team';
pptx.subject = 'Concept-stage innovation evaluation presentation';
pptx.title = 'Huduma SmartAssist';
pptx.company = 'Huduma SmartAssist';
pptx.lang = 'en-KE';
pptx.theme = {
  headFontFace: 'Aptos Display',
  bodyFontFace: 'Aptos',
  lang: 'en-KE'
};
pptx.defineSlideMaster({
  title: 'BASE',
  background: { color: 'F5F4EE' },
  objects: [
    { rect: { x: 0.42, y: 7.13, w: 12.48, h: 0.012, line: { color: 'D8D9D2', transparency: 100 }, fill: { color: 'D8D9D2' } } },
    { text: { text: 'HUDUMA SMARTASSIST  /  CONCEPT PROPOSAL', options: { x: 0.48, y: 7.18, w: 6.8, h: 0.17, fontFace: 'Aptos', fontSize: 8, color: '6E7771', charSpacing: 0.7, margin: 0 } } },
    { text: { text: 'EVALUATION PRESENTATION', options: { x: 9.0, y: 7.18, w: 3.65, h: 0.17, fontFace: 'Aptos', fontSize: 8, color: '6E7771', charSpacing: 0.7, align: 'right', margin: 0 } } }
  ],
  slideNumber: { x: 12.77, y: 7.17, color: '6E7771', fontFace: 'Aptos', fontSize: 8 }
});

const C = {
  ink: '17251F', green: '087F68', greenDark: '075B4D', mint: 'CDE9DC', lime: 'D9EF7A',
  coral: 'EC775F', yellow: 'F1C75B', paper: 'F5F4EE', white: 'FFFFFF', muted: '64716B',
  line: 'D8D9D2', paleGreen: 'E3F1E9', paleCoral: 'F8E7E1', paleYellow: 'F8F0D8',
  blue: '407A88', paleBlue: 'E1EEF0'
};

function text(slide, value, x, y, w, h, opts = {}) {
  slide.addText(value, {
    x, y, w, h, margin: 0, breakLine: false,
    fontFace: opts.fontFace || 'Aptos', fontSize: opts.fontSize || 16,
    color: opts.color || C.ink, bold: opts.bold || false,
    valign: opts.valign || 'mid', align: opts.align || 'left',
    fit: 'shrink', paraSpaceAfterPt: opts.paraSpaceAfterPt || 0,
    bullet: opts.bullet, charSpacing: opts.charSpacing || 0,
    isTextBox: true, transparency: opts.transparency || 0,
    italic: opts.italic || false, breakLine: false
  });
}
function rect(slide, x, y, w, h, fill, radius = 0.08, line = fill) {
  slide.addShape(pptx.ShapeType.roundRect, {
    x, y, w, h, rectRadius: radius,
    line: { color: line, transparency: 100, width: 0.1 },
    fill: { color: fill }
  });
}
function circle(slide, x, y, d, fill) {
  slide.addShape(pptx.ShapeType.ellipse, { x, y, w: d, h: d, line: { color: fill, transparency: 100 }, fill: { color: fill } });
}
function line(slide, x1, y1, x2, y2, color = C.line, width = 1.2, dash) {
  slide.addShape(pptx.ShapeType.line, { x: x1, y: y1, w: x2 - x1, h: y2 - y1, line: { color, width, dashType: dash } });
}
function base(title, kicker, subtitle) {
  const slide = pptx.addSlide('BASE');
  text(slide, kicker.toUpperCase(), 0.52, 0.32, 7.2, 0.2, { fontSize: 9, color: C.green, bold: true, charSpacing: 1.4 });
  text(slide, title, 0.52, 0.67, 12.1, 0.52, { fontFace: 'Aptos Display', fontSize: 29, bold: true });
  if (subtitle) text(slide, subtitle, 0.54, 1.27, 11.9, 0.34, { fontSize: 13, color: C.muted });
  return slide;
}
function label(slide, value, x, y, w, color = C.green, bg = C.paleGreen) {
  rect(slide, x, y, w, 0.31, bg, 0.06);
  text(slide, value.toUpperCase(), x + 0.1, y + 0.015, w - 0.2, 0.27, { fontSize: 8, bold: true, color, charSpacing: 0.7 });
}
function bullet(slide, value, x, y, w, color = C.green, fontSize = 14) {
  circle(slide, x, y + 0.1, 0.09, color);
  text(slide, value, x + 0.2, y, w - 0.2, 0.43, { fontSize, valign: 'top' });
}
function card(slide, x, y, w, h, options = {}) {
  rect(slide, x, y, w, h, options.fill || C.white, 0.08);
  if (options.accent) rect(slide, x, y, 0.07, h, options.accent, 0.02);
  if (options.number) text(slide, options.number, x + 0.2, y + 0.16, 0.48, 0.38, { fontFace: 'Aptos Display', fontSize: 22, bold: true, color: options.accent || C.green });
  if (options.title) text(slide, options.title, x + (options.number ? 0.78 : 0.22), y + 0.17, w - (options.number ? 0.96 : 0.44), 0.38, { fontSize: options.titleSize || 16, bold: true });
  if (options.body) text(slide, options.body, x + 0.22, y + (options.bodyY || 0.68), w - 0.44, h - (options.bodyY || 0.68) - 0.16, { fontSize: options.bodySize || 12, color: C.muted, valign: 'top' });
}

// 1. Cover
{
  const s = pptx.addSlide();
  s.background = { color: C.ink };
  s.addShape(pptx.ShapeType.rect, { x: 8.6, y: 0, w: 4.74, h: 7.5, line: { color: C.greenDark, transparency: 100 }, fill: { color: C.greenDark } });
  s.addShape(pptx.ShapeType.arc, { x: 8.85, y: 0.68, w: 3.8, h: 3.8, adjustPoint: 0.25, line: { color: C.lime, width: 2.4 }, fill: { color: C.greenDark, transparency: 100 }, rotate: 20 });
  circle(s, 9.52, 1.33, 2.35, C.green);
  circle(s, 10.02, 1.83, 1.35, C.lime);
  circle(s, 10.42, 2.23, 0.55, C.ink);
  rect(s, 9.08, 4.25, 3.55, 1.15, C.white, 0.1);
  text(s, 'BOOK  →  ARRIVE  →  SERVED', 9.28, 4.6, 3.15, 0.3, { fontSize: 12, bold: true, color: C.ink, align: 'center', charSpacing: 0.5 });
  text(s, 'HUDUMA KENYA  /  INNOVATION CONCEPT', 0.72, 0.65, 6.6, 0.24, { fontSize: 10, bold: true, color: C.lime, charSpacing: 1.4 });
  text(s, 'Huduma\nSmartAssist', 0.7, 1.55, 7.6, 1.8, { fontFace: 'Aptos Display', fontSize: 45, bold: true, color: C.white, valign: 'top' });
  text(s, 'A clearer route to public services.', 0.74, 3.68, 6.9, 0.48, { fontSize: 20, color: 'C7D5CE' });
  text(s, 'AI-assisted guidance  •  online appointments  •  organised centre visits', 0.75, 4.35, 7.2, 0.65, { fontSize: 14, color: C.white, valign: 'top' });
  label(s, 'Concept proposal · for evaluation', 0.74, 6.42, 3.2, C.ink, C.lime);
  text(s, 'A proposed service-improvement platform. Not yet an operational government system.', 0.75, 6.9, 7.2, 0.27, { fontSize: 10, color: 'AFC0B7' });
}

// 2. Executive summary
{
  const s = base('One trusted front door to Huduma services', 'The proposal', 'Help citizens arrive prepared, spread visits across available capacity, and stay informed after they leave.');
  const items = [
    ['01', 'Understand', 'Plain-language, AI-assisted answers about services, documents and next steps.', C.green],
    ['02', 'Plan a visit', 'Book an available centre and time online; see capacity before travelling.', C.coral],
    ['03', 'Stay informed', 'Receive confirmations and request-status updates through email, with other channels as options.', C.blue]
  ];
  items.forEach((it, i) => card(s, 0.55 + i * 4.15, 2.0, 3.85, 2.13, { number: it[0], title: it[1], body: it[2], accent: it[3], bodySize: 13 }));
  rect(s, 0.55, 4.7, 12.2, 1.23, C.ink, 0.08);
  text(s, 'THE OUTCOME WE ARE AIMING FOR', 0.83, 4.96, 3.4, 0.22, { fontSize: 9, bold: true, color: C.lime, charSpacing: 1.1 });
  text(s, 'More organised visits. Fewer avoidable trips. Better-informed citizens.', 0.83, 5.32, 11.1, 0.37, { fontFace: 'Aptos Display', fontSize: 22, bold: true, color: C.white });
  text(s, 'Proposed outcomes; measure them through a controlled pilot before claiming impact.', 0.58, 6.34, 10.8, 0.27, { fontSize: 11, color: C.muted, italic: true });
}

// 3. Problem
{
  const s = base('A service visit can cost more than the service itself', 'The challenge', 'Unclear information and unmanaged demand create friction for citizens and pressure for centre teams.');
  const problems = [
    ['01', 'Long queues', 'Peak-time demand can mean long waits and crowded waiting areas.', C.coral],
    ['02', 'Unclear requirements', 'Citizens may arrive without the right documents or preparation.', C.yellow],
    ['03', 'Repeat visits', 'Missing information or incomplete applications can send people back.', C.blue],
    ['04', 'No clear updates', 'People may not know whether a request is pending, ready or needs action.', C.green]
  ];
  problems.forEach((p, i) => card(s, 0.56 + (i % 2) * 6.18, 1.9 + Math.floor(i / 2) * 1.75, 5.8, 1.43, { number: p[0], title: p[1], body: p[2], accent: p[3], bodySize: 12 }));
  text(s, 'The opportunity: make each visit more predictable before, during and after a citizen reaches a centre.', 0.62, 5.82, 11.7, 0.55, { fontFace: 'Aptos Display', fontSize: 19, bold: true, color: C.greenDark });
}

// 4. Target users
{
  const s = base('Designed around citizens and the teams serving them', 'Who it serves', 'One service journey, with different tools for the public and authorised centre staff.');
  card(s, 0.6, 1.95, 5.86, 3.63, { title: 'Citizens', body: '• Find a service and understand what to bring\n\n• Choose a centre and request an available appointment\n\n• Receive confirmation and status updates by email\n\n• Share feedback after service', accent: C.green, bodySize: 14 });
  card(s, 6.78, 1.95, 5.86, 3.63, { title: 'Centre teams', body: '• View and manage appointment slots\n\n• Plan daily demand against approved capacity\n\n• Update request status through authorised workflows\n\n• Review service feedback and operational trends', accent: C.coral, bodySize: 14 });
  label(s, 'Access principle', 0.64, 5.98, 1.55, C.greenDark, C.mint);
  text(s, 'Keep a non-digital route available: assisted booking at a centre and accessible alternatives for people without email or internet.', 2.42, 5.98, 9.9, 0.58, { fontSize: 13, color: C.muted });
}

// 5. Solution
{
  const s = base('A practical service companion, not another queue', 'The solution', 'SmartAssist combines service discovery, visit planning, request communication and staff coordination.');
  const modules = [
    ['01', 'Service guide', 'Searchable, plain-language guidance with AI-assisted answers grounded in approved service information.'],
    ['02', 'Appointment booking', 'Online centre, service, date and time selection from published availability.'],
    ['03', 'Request updates', 'Email confirmations and status messages when staff record a change.'],
    ['04', 'Centre operations', 'A staff view for slot capacity, bookings and service-request follow-up.']
  ];
  modules.forEach((m, i) => card(s, 0.6 + (i % 2) * 6.15, 1.92 + Math.floor(i / 2) * 2.1, 5.78, 1.7, { number: m[0], title: m[1], body: m[2], accent: [C.green, C.coral, C.blue, C.yellow][i], bodySize: 12 }));
  text(s, 'Channels can expand over time: responsive web first, with USSD, SMS or WhatsApp subject to partner, accessibility and privacy review.', 0.65, 6.29, 11.9, 0.43, { fontSize: 12, color: C.muted, italic: true });
}

// 6. Citizen journey
{
  const s = base('A simple journey from question to completed visit', 'Citizen experience', 'Each step aims to reduce uncertainty and give people a clear next action.');
  const steps = [
    ['1', 'Find', 'Choose a service or ask a question'],
    ['2', 'Prepare', 'See requirements and what to bring'],
    ['3', 'Book', 'Select a centre and open time slot'],
    ['4', 'Arrive', 'Show booking details; staff assist'],
    ['5', 'Track', 'Get email updates and share feedback']
  ];
  steps.forEach((st, i) => {
    const x = 0.62 + i * 2.49;
    circle(s, x + 0.4, 2.08, 0.68, i === 2 ? C.coral : C.green);
    text(s, st[0], x + 0.4, 2.17, 0.68, 0.42, { fontSize: 19, bold: true, color: C.white, align: 'center' });
    if (i < steps.length - 1) line(s, x + 1.13, 2.42, x + 2.78, 2.42, C.line, 2.0);
    text(s, st[1], x, 3.02, 1.65, 0.32, { fontSize: 16, bold: true, align: 'center' });
    text(s, st[2], x - 0.18, 3.47, 2.0, 0.88, { fontSize: 11, color: C.muted, align: 'center', valign: 'top' });
  });
  rect(s, 0.62, 5.16, 12.02, 0.98, C.paleYellow, 0.08);
  text(s, 'Design promise', 0.9, 5.42, 1.6, 0.26, { fontSize: 13, bold: true, color: C.ink });
  text(s, 'Always show what is confirmed, what still needs staff action and how to get help.', 2.65, 5.42, 9.6, 0.26, { fontSize: 14, color: C.ink });
}

// 7. Booking and capacity
{
  const s = base('Bookings follow the capacity a centre can actually serve', 'Appointments & capacity', 'Published slots are controlled by each participating centre, not by an unconstrained booking form.');
  const days = ['MON', 'TUE', 'WED', 'THU', 'FRI'];
  const slots = [
    ['09:00', 'OPEN', 'FULL', 'OPEN', 'OPEN', 'OPEN'],
    ['11:00', 'OPEN', 'OPEN', 'OPEN', 'FULL', 'OPEN'],
    ['14:00', 'FULL', 'OPEN', 'OPEN', 'OPEN', 'OPEN']
  ];
  text(s, 'EXAMPLE SLOT VIEW', 0.67, 1.83, 3.0, 0.23, { fontSize: 9, bold: true, color: C.green, charSpacing: 1 });
  days.forEach((d, i) => text(s, d, 2.35 + i * 1.05, 2.18, 0.9, 0.25, { fontSize: 10, bold: true, color: C.muted, align: 'center' }));
  slots.forEach((row, r) => {
    text(s, row[0], 0.76, 2.68 + r * 0.7, 1.0, 0.29, { fontSize: 12, bold: true });
    row.slice(1).forEach((value, i) => {
      const x = 2.31 + i * 1.05;
      rect(s, x, 2.65 + r * 0.7, 0.88, 0.42, value === 'FULL' ? C.paleCoral : C.paleGreen, 0.06);
      text(s, value, x + 0.04, 2.71 + r * 0.7, 0.8, 0.24, { fontSize: 9, bold: true, color: value === 'FULL' ? 'A94B3C' : C.greenDark, align: 'center' });
    });
  });
  card(s, 8.6, 1.95, 3.92, 3.25, { title: 'How crowding is reduced', body: '• Set slot limits with centre teams\n\n• Close a slot when approved capacity is reached\n\n• Offer other open times or centres\n\n• Monitor arrivals and no-shows', accent: C.coral, bodySize: 13 });
  text(s, 'Capacity is a planning aid, not a guarantee of walk-in wait time. Pilot teams must define staffing, exceptions and walk-in handling.', 0.68, 5.65, 11.7, 0.63, { fontSize: 12, color: C.muted, italic: true });
}

// 8. Email notifications
{
  const s = base('Citizens should not have to guess what happens next', 'Email & request feedback', 'A notification is triggered by a confirmed booking or a status change entered through an authorised workflow.');
  const events = [
    ['BOOKING CONFIRMED', 'Centre, date, time and reference number', C.green],
    ['REMINDER', 'Appointment reminder and preparation checklist', C.yellow],
    ['STATUS CHANGED', 'Pending, in progress, completed or action needed', C.blue],
    ['FEEDBACK REQUESTED', 'Simple service rating and optional comment', C.coral]
  ];
  events.forEach((ev, i) => {
    const y = 1.95 + i * 0.98;
    circle(s, 0.75, y + 0.1, 0.28, ev[2]);
    text(s, ev[0], 1.22, y, 2.45, 0.3, { fontSize: 10, bold: true, color: ev[2], charSpacing: 0.5 });
    text(s, ev[1], 3.7, y, 4.4, 0.42, { fontSize: 13 });
    if (i < events.length - 1) line(s, 0.89, y + 0.41, 0.89, y + 0.92, C.line, 1.4);
  });
  rect(s, 8.55, 2.0, 3.9, 2.95, C.ink, 0.1);
  text(s, 'SERVICE REQUEST', 8.84, 2.3, 3.2, 0.25, { fontSize: 9, bold: true, color: C.lime, charSpacing: 1 });
  text(s, 'Your request is\nin progress', 8.84, 2.81, 3.1, 0.95, { fontFace: 'Aptos Display', fontSize: 25, bold: true, color: C.white, valign: 'top' });
  text(s, 'We will email you when its status changes.', 8.85, 4.12, 3.05, 0.46, { fontSize: 12, color: 'C7D5CE', valign: 'top' });
  text(s, 'Email delivery and request tracking depend on integration with participating service teams. Do not send sensitive documents by email.', 0.68, 6.15, 11.6, 0.44, { fontSize: 11, color: C.muted, italic: true });
}

// 9. Centre operations
{
  const s = base('A shared operational picture helps teams stay organised', 'Centre operations', 'A proposed staff dashboard gives authorised teams a day-to-day view of visits and follow-ups.');
  const metrics = [['TODAY', '64', 'bookings'], ['CAPACITY', '82%', 'planned'], ['FOLLOW-UP', '12', 'requests']];
  metrics.forEach((m, i) => {
    rect(s, 0.63 + i * 2.13, 1.95, 1.92, 1.2, [C.paleGreen, C.paleYellow, C.paleBlue][i], 0.08);
    text(s, m[0], 0.81 + i * 2.13, 2.12, 1.55, 0.2, { fontSize: 8, bold: true, color: C.muted, charSpacing: 0.7 });
    text(s, m[1], 0.81 + i * 2.13, 2.4, 0.76, 0.4, { fontFace: 'Aptos Display', fontSize: 23, bold: true });
    text(s, m[2], 1.58 + i * 2.13, 2.48, 0.82, 0.24, { fontSize: 10, color: C.muted });
  });
  rect(s, 7.18, 1.95, 5.42, 3.7, C.white, 0.08);
  text(s, 'SERVICE DESK  /  TODAY', 7.48, 2.2, 4.5, 0.24, { fontSize: 9, bold: true, color: C.green, charSpacing: 0.8 });
  const rowData = [['09:00', 'Appointments', 'On track'], ['10:30', 'Document services', 'Busy'], ['12:00', 'Break / walk-ins', 'Planned'], ['14:00', 'Request follow-up', '12 open']];
  rowData.forEach((r, i) => {
    const y = 2.68 + i * 0.62;
    if (i > 0) line(s, 7.48, y - 0.1, 12.28, y - 0.1, C.line, 0.7);
    text(s, r[0], 7.49, y, 0.72, 0.24, { fontSize: 10, bold: true });
    text(s, r[1], 8.37, y, 2.15, 0.24, { fontSize: 11 });
    text(s, r[2], 10.73, y, 1.48, 0.24, { fontSize: 10, color: C.greenDark, align: 'right' });
  });
  text(s, 'Operational measures should support staff decisions; they are not a substitute for local judgement or service standards.', 0.67, 5.95, 11.5, 0.4, { fontSize: 12, color: C.muted, italic: true });
}

// 10. Technical architecture
{
  const s = base('A modular platform connects citizens to authorised services', 'Proposed architecture', 'Start with secure core workflows; add agency and channel integrations only after approval and testing.');
  const layers = [
    { x: 0.65, y: 1.95, w: 2.48, title: 'CITIZEN CHANNELS', body: 'Responsive web\nAssisted service desk\nOptional SMS / USSD', fill: C.paleGreen, accent: C.green },
    { x: 3.65, y: 1.95, w: 2.48, title: 'SMARTASSIST', body: 'Service catalogue\nBooking & capacity\nRequest notifications', fill: C.paleYellow, accent: C.yellow },
    { x: 6.65, y: 1.95, w: 2.48, title: 'SECURE PLATFORM', body: 'APIs & identity\nApplication services\nAudit & monitoring', fill: C.paleBlue, accent: C.blue },
    { x: 9.65, y: 1.95, w: 2.88, title: 'APPROVED PARTNERS', body: 'Centre operations\nEmail / telecom provider\nGovernment systems', fill: C.paleCoral, accent: C.coral }
  ];
  layers.forEach((l) => {
    rect(s, l.x, l.y, l.w, 2.3, l.fill, 0.08);
    rect(s, l.x, l.y, l.w, 0.09, l.accent, 0.03);
    text(s, l.title, l.x + 0.2, l.y + 0.28, l.w - 0.4, 0.26, { fontSize: 9, bold: true, color: l.accent, charSpacing: 0.7 });
    text(s, l.body, l.x + 0.2, l.y + 0.81, l.w - 0.4, 1.12, { fontSize: 13, bold: true, valign: 'top' });
  });
  [3.18, 6.18, 9.18].forEach((x) => {
    line(s, x, 3.02, x + 0.42, 3.02, C.ink, 1.6);
    s.addShape(pptx.ShapeType.chevron, { x: x + 0.24, y: 2.89, w: 0.23, h: 0.25, line: { color: C.ink, transparency: 100 }, fill: { color: C.ink } });
  });
  rect(s, 0.66, 4.75, 11.86, 0.9, C.ink, 0.08);
  text(s, 'FOUNDATION', 0.92, 5.06, 1.33, 0.2, { fontSize: 9, bold: true, color: C.lime, charSpacing: 0.8 });
  text(s, 'Cloud/server hosting  ·  database  ·  security controls  ·  backups  ·  support', 2.34, 5.02, 9.75, 0.3, { fontSize: 13, color: C.white });
  text(s, 'AI is an assistive layer: approved content, uncertainty handling and a human route for consequential decisions.', 0.69, 6.09, 11.4, 0.38, { fontSize: 11, color: C.muted, italic: true });
}

// 11. Privacy and safeguards
{
  const s = base('Trust is a product requirement', 'Privacy, security & inclusion', 'The design must protect citizens and staff from avoidable data, access and service risks.');
  const principles = [
    ['MINIMISE', 'Collect only what a booking or approved request workflow needs.', C.green],
    ['PROTECT', 'Use access controls, encryption, backups and security monitoring.', C.blue],
    ['BE CLEAR', 'Explain how information is used and how to get human help.', C.coral],
    ['BE FAIR', 'Test language access, accessibility and performance across user groups.', C.yellow]
  ];
  principles.forEach((p, i) => card(s, 0.64 + (i % 2) * 6.1, 1.93 + Math.floor(i / 2) * 1.75, 5.75, 1.4, { title: p[0], body: p[1], accent: p[2], bodyY: 0.57, bodySize: 12 }));
  rect(s, 0.66, 5.77, 11.85, 0.66, C.paleCoral, 0.06);
  text(s, 'Before pilot: complete legal, data-protection, security and agency approval reviews; define retention, incident response and consent.', 0.88, 5.93, 11.4, 0.34, { fontSize: 11, bold: true, color: C.ink });
}

// 12. Business model and cost
{
  const s = base('Sustainability through shared public value', 'Business model & costs', 'A potential public-private partnership model; commercial terms and procurement route require validation.');
  text(s, 'POTENTIAL REVENUE / FUNDING', 0.7, 1.9, 5.3, 0.28, { fontSize: 10, bold: true, color: C.green, charSpacing: 0.8 });
  bullet(s, 'Institutional subscription or platform licence', 0.75, 2.36, 5.1, C.green, 13);
  bullet(s, 'Implementation, integration and maintenance contracts', 0.75, 3.07, 5.1, C.green, 13);
  bullet(s, 'Approved API/service integration and customisation fees', 0.75, 3.78, 5.1, C.green, 13);
  bullet(s, 'Partner support for messaging, connectivity or cloud services', 0.75, 4.49, 5.1, C.green, 13);
  line(s, 6.36, 1.94, 6.36, 5.72, C.line, 1.3);
  text(s, 'COST DRIVERS', 6.82, 1.9, 4.6, 0.28, { fontSize: 10, bold: true, color: C.coral, charSpacing: 0.8 });
  bullet(s, 'Product design, software and AI/NLP development', 6.88, 2.36, 5.05, C.coral, 13);
  bullet(s, 'Hosting, database, AI inference and backups', 6.88, 3.07, 5.05, C.coral, 13);
  bullet(s, 'USSD/SMS/email charges and system integrations', 6.88, 3.78, 5.05, C.coral, 13);
  bullet(s, 'Cybersecurity, training, maintenance and user support', 6.88, 4.49, 5.05, C.coral, 13);
  rect(s, 0.72, 5.57, 11.83, 0.67, C.ink, 0.07);
  text(s, 'ACCESS PRINCIPLE', 0.95, 5.79, 1.75, 0.2, { fontSize: 9, bold: true, color: C.lime, charSpacing: 0.7 });
  text(s, 'Keep basic citizen guidance and access free or low-cost wherever feasible.', 2.82, 5.75, 9.2, 0.26, { fontSize: 13, bold: true, color: C.white });
}

// 13. Impact and measurement
{
  const s = base('Prove the value with a measured pilot', 'Outcomes & evaluation', 'Establish a baseline first; compare pilot results with agreed centre and citizen-service measures.');
  const measures = [
    ['WAIT', 'Median time from arrival to service'],
    ['ACCESS', 'Share of visitors who arrive prepared'],
    ['FLOW', 'Booking attendance, no-shows and slot fill rate'],
    ['RESOLUTION', 'Repeat visits and request completion time'],
    ['EXPERIENCE', 'Citizen satisfaction and feedback response'],
    ['EQUITY', 'Use and outcomes across access channels']
  ];
  measures.forEach((m, i) => {
    const col = i % 3, row = Math.floor(i / 3), x = 0.65 + col * 4.12, y = 2.02 + row * 1.65;
    rect(s, x, y, 3.82, 1.3, [C.paleGreen, C.paleYellow, C.paleBlue, C.paleCoral, C.paleGreen, C.paleYellow][i], 0.08);
    text(s, m[0], x + 0.2, y + 0.2, 1.2, 0.22, { fontSize: 9, bold: true, color: [C.green, C.coral, C.blue, C.coral, C.green, C.greenDark][i], charSpacing: 0.8 });
    text(s, m[1], x + 0.2, y + 0.56, 3.4, 0.55, { fontSize: 12, bold: true, valign: 'top' });
  });
  text(s, 'Success is not downloads. It is fewer avoidable waits and trips, without excluding people who need assisted service.', 0.68, 5.78, 11.6, 0.52, { fontFace: 'Aptos Display', fontSize: 17, bold: true, color: C.greenDark });
}

// 14. Roadmap
{
  const s = base('Start small, validate, then scale responsibly', 'Implementation roadmap', 'A phased path turns a promising concept into a service that is tested with real users and operational teams.');
  const phases = [
    ['01', 'DISCOVER', 'Co-design with citizens, Huduma teams and agencies; map priority services and current demand.', C.green],
    ['02', 'PROTOTYPE', 'Test service guidance, booking and notification journeys with representative users.', C.yellow],
    ['03', 'PILOT', 'Run a limited-centre pilot; train staff; measure baseline and outcomes; resolve issues.', C.coral],
    ['04', 'SCALE', 'Expand only with approvals, proven capacity workflows, support plans and sustainable funding.', C.blue]
  ];
  phases.forEach((p, i) => {
    const x = 0.66 + i * 3.06;
    circle(s, x + 0.04, 2.05, 0.56, p[3]);
    text(s, p[0], x + 0.04, 2.16, 0.56, 0.28, { fontSize: 14, bold: true, color: C.white, align: 'center' });
    if (i < 3) line(s, x + 0.67, 2.33, x + 3.0, 2.33, C.line, 1.6);
    text(s, p[1], x, 2.93, 2.7, 0.31, { fontSize: 13, bold: true, color: p[3], charSpacing: 0.7 });
    text(s, p[2], x, 3.47, 2.6, 1.37, { fontSize: 12, color: C.muted, valign: 'top' });
  });
  rect(s, 0.67, 5.5, 11.9, 0.82, C.ink, 0.08);
  text(s, 'PILOT GATE', 0.95, 5.8, 1.38, 0.2, { fontSize: 9, bold: true, color: C.lime, charSpacing: 0.8 });
  text(s, 'Agency approval  ·  privacy/security review  ·  trained staff  ·  agreed service capacity  ·  baseline measures', 2.43, 5.75, 9.82, 0.31, { fontSize: 11, color: C.white });
}

// 15. Closing
{
  const s = pptx.addSlide();
  s.background = { color: C.greenDark };
  circle(s, 9.56, 0.76, 2.72, C.lime);
  circle(s, 10.14, 1.34, 1.56, C.green);
  circle(s, 10.57, 1.77, 0.7, C.greenDark);
  text(s, 'THE ASK', 0.76, 0.78, 2.2, 0.25, { fontSize: 10, bold: true, color: C.lime, charSpacing: 1.4 });
  text(s, 'Let’s make every\nHuduma visit count.', 0.72, 1.55, 8.5, 1.55, { fontFace: 'Aptos Display', fontSize: 38, bold: true, color: C.white, valign: 'top' });
  text(s, 'We are seeking a pilot pathway with Huduma Centre teams and relevant agencies to validate citizen needs, capacity rules, service information and measurable outcomes.', 0.77, 3.7, 7.8, 0.95, { fontSize: 17, color: 'E1EFE8', valign: 'top' });
  rect(s, 0.78, 5.12, 7.55, 0.79, C.ink, 0.08);
  text(s, 'GUIDE  →  BOOK  →  INFORM  →  IMPROVE', 1.03, 5.39, 7.0, 0.25, { fontSize: 13, bold: true, color: C.lime, charSpacing: 0.7 });
  text(s, 'Huduma SmartAssist  /  A concept proposal for evaluation', 0.8, 6.76, 8.8, 0.25, { fontSize: 10, color: 'C3DBCF' });
}

pptx.writeFile({ fileName: path.join(__dirname, 'Huduma_SmartAssist_Evaluation_Presentation.pptx') });