<?php
declare(strict_types=1);

/**
 * Government services available online through eCitizen (and a few partner
 * portals), with guidance for getting assisted help at a Huduma Centre.
 *
 * IMPORTANT: requirements and steps are general guidance only. Fees change, so
 * they are deliberately not stated: citizens see the official fee on the portal
 * when they apply. Have each agency confirm details before a real pilot.
 *
 * Each row: slug, name, agency, category, summary, requirements, steps,
 *           fee, processing time, keywords, bookable (assisted visit), apply URL
 */

const ECITIZEN = 'https://www.ecitizen.go.ke';
const FEE_ON_PORTAL = 'Official fee shown on eCitizen when you apply';

return [
    // ---- eCitizen account ----
    ['ecitizen-account', 'eCitizen account registration & support', 'eCitizen', 'Getting started',
        'Create an eCitizen account, reset a forgotten password or update your phone and email. You need an account for most government services online.',
        "Original national ID card (or alien card / passport for foreigners)\nA phone number you can receive SMS on\nAn email address you can access",
        "Go to eCitizen and choose Create account\nEnter your ID details, phone and email\nConfirm the one-time code sent by SMS\nSet a strong password and sign in\nIf you get stuck, book a Huduma Centre visit and an officer will help",
        'Free', 'Same day', 'ecitizen account register sign up password reset login akaunti jisajili', 1, ECITIZEN],

    // ---- Department of Immigration Services ----
    ['passport-replacement', 'Passport replacement (lost, damaged or full)', 'Department of Immigration Services', 'Travel & immigration',
        'Replace a passport that is lost, stolen, damaged or has no blank pages.',
        "eCitizen application and payment receipt\nOriginal national ID card\nPolice abstract (if lost or stolen)\nThe old or damaged passport (if you have it)",
        "Report a lost passport to the police and get an abstract\nApply and pay on eCitizen under Immigration\nBook a biometrics appointment\nAttend for fingerprints and photo\nTrack and collect when ready",
        FEE_ON_PORTAL, 'Several weeks; track on eCitizen', 'passport lost stolen damaged replace pasipoti imepotea', 1, ECITIZEN],
    ['passport-minor', 'Passport for a child (under 18)', 'Department of Immigration Services', 'Travel & immigration',
        'Apply for a passport for a child, with a parent or guardian present.',
        "eCitizen application and payment receipt\nChild's original birth certificate\nParents' or guardian's national ID cards\nConsent from both parents where required\nRecent passport-size photos of the child",
        "Apply and pay on eCitizen\nBook a biometrics appointment\nAttend with the child and a parent or guardian\nTrack and collect when ready",
        FEE_ON_PORTAL, 'Several weeks; track on eCitizen', 'passport child minor baby kid mtoto pasipoti', 1, ECITIZEN],
    ['work-permit', 'Work permit & pass for foreign nationals', 'Department of Immigration Services', 'Travel & immigration',
        'Apply for or renew a work permit, dependant pass or special pass for a foreign national.',
        "eCitizen account for the applicant\nValid passport\nSupporting documents for the permit class (e.g. employer letter, academic certificates)\nPayment on eCitizen",
        "Choose the permit class on eCitizen\nUpload the supporting documents\nPay the processing fee\nTrack the decision on eCitizen",
        FEE_ON_PORTAL, 'Varies by permit class', 'work permit foreign national expatriate dependant pass immigration kibali', 1, ECITIZEN],
    ['foreign-national-card', 'Foreign national registration (alien card)', 'Department of Immigration Services', 'Travel & immigration',
        'Register as a foreign national living in Kenya and get a foreign national ID card.',
        "Valid passport\nValid work permit or pass\neCitizen application and payment receipt",
        "Apply and pay on eCitizen\nBook an appointment for fingerprints and photo\nCollect the card when notified",
        FEE_ON_PORTAL, 'Several weeks', 'alien card foreign national registration id foreigner', 1, ECITIZEN],
    ['kenyan-citizenship', 'Kenyan citizenship & dual citizenship', 'Department of Immigration Services', 'Travel & immigration',
        'Apply for registration as a Kenyan citizen, or declare dual citizenship.',
        "eCitizen application\nPassport and identity documents\nProof of eligibility (e.g. marriage, residence or birth records)\nPayment on eCitizen",
        "Check your eligibility on eCitizen\nComplete and pay for the application\nAttend interviews if requested\nTrack the decision on eCitizen",
        FEE_ON_PORTAL, 'Several months', 'citizenship dual citizen naturalisation uraia', 0, ECITIZEN],
    ['electronic-travel-authorisation', 'Electronic Travel Authorisation (eTA) for visitors', 'Department of Immigration Services', 'Travel & immigration',
        'Visitors to Kenya apply online for an Electronic Travel Authorisation before they travel.',
        "Valid passport\nTravel itinerary and accommodation details\nOnline payment card",
        "Apply on the official eTA portal\nUpload a photo and passport page\nPay the fee online\nReceive the eTA by email",
        'Official fee shown on the eTA portal', 'Usually within a few days', 'visa eta travel authorisation tourist visitor', 0, 'https://www.etakenya.go.ke'],

    // ---- Civil Registration Services ----
    ['death-certificate', 'Death certificate', 'Civil Registration Services', 'Identity & registration',
        'Apply for a death certificate for a registered death.',
        "Burial permit or death notification\nDeceased person's ID card copy\nApplicant's ID card\neCitizen payment receipt",
        "Apply and pay on eCitizen\nBring the original documents for verification if asked\nCollect the certificate when notified",
        FEE_ON_PORTAL, 'About 1–3 weeks', 'death certificate deceased burial cheti kifo', 1, ECITIZEN],
    ['late-birth-registration', 'Late birth registration', 'Civil Registration Services', 'Identity & registration',
        'Register a birth that was not registered within six months, so a birth certificate can be issued.',
        "Proof of birth (clinic card, baptism card or school records)\nParents' ID card copies\nLetter from the chief (if required)\nApplication form",
        "Visit a registration office or book a Huduma Centre visit\nSubmit the proof of birth and forms\nPay the fee\nApply for the birth certificate once registered",
        FEE_ON_PORTAL, 'A few weeks', 'late birth registration certificate cheti kuzaliwa adult', 1, ECITIZEN],

    // ---- Directorate of Criminal Investigations / National Police Service ----
    ['police-abstract', 'Police abstract', 'National Police Service', 'Security & clearance',
        'Request a police abstract after reporting a loss or an incident, for example a lost ID or passport.',
        "Occurrence Book (OB) number from the police station where you reported\nYour national ID card (or a copy)\neCitizen account",
        "Report the loss or incident at a police station and get an OB number\nApply for the abstract on eCitizen\nPay the fee and download or collect the abstract",
        FEE_ON_PORTAL, 'Same day to a few days', 'police abstract lost ob report polisi', 0, ECITIZEN],

    // ---- National Transport and Safety Authority ----
    ['provisional-driving-licence', 'Provisional driving licence (learner)', 'National Transport and Safety Authority', 'Transport',
        'Apply for a provisional (learner) driving licence before driving school and the driving test.',
        "eCitizen account\nNational ID card\nDriving school details\nPayment on eCitizen",
        "Apply on eCitizen under NTSA\nPay the fee\nDownload the provisional licence\nBook driving lessons and the test through your school",
        FEE_ON_PORTAL, 'Same day', 'provisional driving licence learner pdl ntsa test', 1, ECITIZEN],
    ['motor-vehicle-transfer', 'Motor vehicle ownership transfer', 'National Transport and Safety Authority', 'Transport',
        'Transfer a vehicle from the seller to the buyer.',
        "Seller's and buyer's eCitizen accounts\nKRA PINs of both parties\nVehicle logbook details\nPayment on eCitizen",
        "Seller starts the transfer on eCitizen\nBuyer accepts the transfer\nPay the transfer fee\nThe new logbook is issued to the buyer",
        FEE_ON_PORTAL, 'A few days', 'vehicle transfer logbook car buy sell gari ntsa', 1, ECITIZEN],
    ['motor-vehicle-search', 'Motor vehicle search', 'National Transport and Safety Authority', 'Transport',
        'Check a vehicle\'s registered owner and details before buying it.',
        "eCitizen account\nThe vehicle registration number\nPayment on eCitizen",
        "Search the vehicle on eCitizen under NTSA\nPay the search fee\nDownload the search results",
        FEE_ON_PORTAL, 'Same day', 'vehicle search logbook owner car check', 0, ECITIZEN],
    ['vehicle-inspection', 'Motor vehicle inspection booking', 'National Transport and Safety Authority', 'Transport',
        'Book an inspection for a commercial or imported vehicle.',
        "eCitizen account\nVehicle registration details\nPayment on eCitizen",
        "Apply for inspection on eCitizen\nPay the fee and choose an inspection centre\nTake the vehicle for inspection on the booked date",
        FEE_ON_PORTAL, 'Depends on inspection centre', 'inspection vehicle psv commercial ukaguzi', 0, ECITIZEN],

    // ---- Business Registration Service ----
    ['company-registration', 'Private limited company registration', 'Business Registration Service', 'Business',
        'Register a private limited company.',
        "eCitizen account\nDirectors' and shareholders' IDs and KRA PINs\nReserved company name\nRegistered office address\nPayment on eCitizen",
        "Reserve the company name on eCitizen\nComplete the registration forms\nAdd directors and shareholders\nPay the fee\nDownload the certificate of incorporation",
        FEE_ON_PORTAL, 'A few working days', 'company registration limited ltd incorporation kampuni brs', 1, ECITIZEN],
    ['company-search-cr12', 'Official company search (CR12)', 'Business Registration Service', 'Business',
        'Get an official search showing a company\'s directors and shareholders.',
        "eCitizen account\nCompany name or registration number\nPayment on eCitizen",
        "Request the official search on eCitizen\nPay the fee\nDownload the CR12 when ready",
        FEE_ON_PORTAL, '1–3 working days', 'cr12 company search directors shareholders', 0, ECITIZEN],
    ['annual-returns', 'Company annual returns filing', 'Business Registration Service', 'Business',
        'File a company\'s annual returns to stay compliant.',
        "eCitizen account linked to the company\nCompany financial statements (where required)\nPayment on eCitizen",
        "Sign in to the company on eCitizen\nFile the annual return for each year due\nPay the filing fee",
        FEE_ON_PORTAL, 'Same day to a few days', 'annual returns company filing compliance', 1, ECITIZEN],

    // ---- Kenya Revenue Authority ----
    ['tax-compliance-certificate', 'Tax Compliance Certificate', 'Kenya Revenue Authority', 'Tax',
        'Apply for a Tax Compliance Certificate, often needed for jobs and tenders.',
        "KRA PIN and iTax password\nAll tax returns filed\nNo outstanding tax balance",
        "Sign in to iTax\nFile any missing returns\nApply for the certificate under Certificates\nDownload it once approved",
        'Free', 'Usually same day if compliant', 'tax compliance certificate tcc kra itax', 1, 'https://itax.kra.go.ke'],

    // ---- Higher Education Loans Board ----
    ['helb-compliance', 'HELB compliance certificate', 'Higher Education Loans Board', 'Education',
        'Get a HELB compliance (clearance) certificate showing your loan is up to date or fully repaid.',
        "HELB portal account\nNational ID number\nPayment for the certificate (if charged)",
        "Sign in to the HELB portal\nRequest the compliance certificate\nPay any charge\nDownload the certificate",
        'Official fee shown on the HELB portal', 'Same day to a few days', 'helb clearance compliance certificate loan', 0, 'https://www.helb.co.ke'],

    // ---- Registrar of Marriages ----
    ['notice-of-marriage', 'Notice of marriage (civil marriage)', 'Office of the Registrar of Marriages', 'Identity & registration',
        'Give notice of an intended civil marriage and book the ceremony.',
        "eCitizen accounts for both parties\nIDs or passports of both parties\nDivorce decree or death certificate (if previously married)\nTwo witnesses' IDs",
        "Give notice of marriage on eCitizen\nPay the fee\nWait for the notice period to pass\nAttend the ceremony at the registrar's office",
        FEE_ON_PORTAL, 'At least 21 days notice period', 'marriage notice civil wedding ndoa registrar', 0, ECITIZEN],

    // ---- Registrar of Societies ----
    ['society-registration', 'Society, club or association registration', 'Registrar of Societies', 'Business',
        'Register a society, welfare group, club or association.',
        "eCitizen account\nConstitution of the society\nOfficials' IDs and minutes of the founding meeting\nPayment on eCitizen",
        "Search and reserve the name on eCitizen\nUpload the constitution and minutes\nPay the fee\nDownload the certificate once approved",
        FEE_ON_PORTAL, 'A few weeks', 'society registration club association welfare chama group', 0, ECITIZEN],

    // ---- Ministry of Lands ----
    ['land-search', 'Land title search', 'Ministry of Lands and Physical Planning', 'Land & property',
        'Check who owns a parcel of land and whether there are charges on it.',
        "Online land portal account\nLand title or parcel number\nOwner's consent where required\nPayment online",
        "Sign in to the land portal\nRequest an official search for the parcel\nPay the fee\nDownload the search results",
        'Official fee shown on the land portal', 'Same day to a few days', 'land search title deed plot shamba ardhi parcel', 0, 'https://ardhisasa.lands.go.ke'],

    // ---- Kenya National Examinations Council ----
    ['knec-certificate', 'Exam certificate replacement or verification', 'Kenya National Examinations Council', 'Education',
        'Request a replacement for a lost KCPE/KCSE certificate, or have a certificate verified.',
        "National ID card\nIndex number and year of the exam\nPolice abstract (for a lost certificate)\nPayment as directed by KNEC",
        "Submit the request to KNEC\nPay the fee\nCollect the replacement or verification result when notified",
        'Official fee set by KNEC', 'Several weeks', 'knec kcse kcpe certificate lost replacement verification cheti mtihani', 0, 'https://www.knec.ac.ke'],

    // ---- County governments ----
    ['single-business-permit', 'Single business permit (county)', 'County governments', 'Business',
        'Apply for or renew the annual business permit from your county government.',
        "Business registration certificate\nKRA PIN\nBusiness location details\nPayment to the county",
        "Apply through your county's online service on eCitizen, or at the county offices\nPay the permit fee\nDownload or collect the permit",
        'Set by each county', 'Same day to a few days', 'business permit county licence single sbp biashara', 0, ECITIZEN],
];
