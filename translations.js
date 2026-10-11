// ==========================================================================
// FindARescue.ie — Translations and language switching (all pages)
// ==========================================================================
// How it works
// - Every UI string lives here under a short key, once in "en" and once in
//   "ga". Both objects must have the same keys.
// - HTML opts in with attributes, applied by applyTranslations():
//     data-i18n="key"             → element text
//     data-i18n-placeholder="key" → input placeholder
//     data-i18n-aria="key"        → aria-label (icon-only buttons, groups)
//     data-src-en / data-src-ga   → image swapped per language (logos)
// - t("key") returns the string for the current language, falling back to
//   English, then to the key itself. It is used with textContent, so strings
//   must be plain text — no HTML.
// - Rescue descriptions are translated per entry in resources.js with an
//   optional "description_ga" field; English is shown when it's missing.
//
// Irish translations are always written by hand. A "ga" value that is still
// an English placeholder is marked  // TODO (ga)  — search for that marker.
// ==========================================================================

const translations = {
  en: {
    // Navigation
    navHome: "Home",
    navMap: "Map",
    navAbout: "About",
    langToggleAriaEn: "Switch to Irish",
    langToggleAriaGa: "Switch to English",

    // index.html — header
    homeTagline: "Ireland's most comprehensive animal rescue database.",
    homeFeedback: "Feedback and queries: info@findarescue.ie", // unused: kept for reuse
    safetyLinkText: "Online safety information",

    // index.html — safety modal
    safetyModalTitle: "Online safety policy",
    safetyModalP1: "FindARescue.ie helps connect people with animal rescues and rehoming organisations. We do not vet, endorse, or verify the organisations listed on this website.",
    safetyModalP2: "Before donating, adopting, fostering, or sharing personal information, we recommend that you:",
    safetyModalLi1: "Research the organisation independently.",
    safetyModalLi2: "Check for a website, social media presence, and contact details.",
    safetyModalLi3: "If the organisation claims to be a registered charity, verify its Registered Charity Number (RCN) on the Charities Regulator's public register.",
    safetyModalLi4: "Ask questions about the animal's history, health, and care.",
    safetyModalLi5: "Request veterinary records or adoption paperwork where appropriate.",
    safetyModalLi6: "Visit the rescue or arrange a video call if possible.",
    safetyModalLi7: "Be cautious of requests for unusual payment methods or urgent payments.",
    safetyModalLi8: "Never share sensitive financial or personal information unless necessary and you are confident the organisation is legitimate.",
    safetyModalP3: "If something seems suspicious, trust your judgement and discontinue contact.",
    safetyModalP4: "FindARescue.ie is not responsible for transactions, agreements, communications, or disputes between users and organisations listed on the site.",

    // index.html — search & filters
    searchPlaceholder: "Search rescues…",
    searchInfoTooltip: "Type keywords to find specific organisations. You can also filter by category or location.", // unused: kept for reuse
    filterLocationDefault: "Filter by location",
    filterAllLocations: "All locations", // unused: kept for reuse
    locationClearAll: "Clear all",
    locationDone: "Done",
    locationRemove: "Remove",
    resetTooltip: "Reset filters.", // unused: kept for reuse
    noResults: "No resources found. Try resetting the filter options by clicking \ud83d\udd04.", // unused: kept for reuse
    websiteLink: "Website",
    linkUnavailable: "not available",
    facebookLink: "Facebook",
    instagramLink: "Instagram",
    charityBadgeTooltip: "Registered charity number: ",

    // Categories
    catAll: "All",
    catDogs: "Dogs",
    catCats: "Cats",
    catBirds: "Birds",
    catSmallAnimals: "Small animals",
    catEquines: "Equines",
    catWildlife: "Wildlife",

    // Location filter dropdown — provinces + all 32 counties (+ NI note not needed, values stay English for data matching)
    locConnacht: "Connacht",
    locLeinster: "Leinster",
    locMunster: "Munster",
    locUlster: "Ulster",
    locAntrim: "Antrim",
    locArmagh: "Armagh",
    locCarlow: "Carlow",
    locCavan: "Cavan",
    locClare: "Clare",
    locCork: "Cork",
    locDerry: "Derry",
    locDonegal: "Donegal",
    locDown: "Down",
    locDublin: "Dublin",
    locFermanagh: "Fermanagh",
    locGalway: "Galway",
    locKerry: "Kerry",
    locKildare: "Kildare",
    locKilkenny: "Kilkenny",
    locLaois: "Laois",
    locLeitrim: "Leitrim",
    locLimerick: "Limerick",
    locLongford: "Longford",
    locLouth: "Louth",
    locMayo: "Mayo",
    locMeath: "Meath",
    locMonaghan: "Monaghan",
    locOffaly: "Offaly",
    locRoscommon: "Roscommon",
    locSligo: "Sligo",
    locTipperary: "Tipperary",
    locTyrone: "Tyrone",
    locWaterford: "Waterford",
    locWestmeath: "Westmeath",
    locWexford: "Wexford",
    locWicklow: "Wicklow",

    // Footer
    footerCopyright: "Copyright FindARescue.ie 2026",

    // overlay.html (map disclaimer)
    overlayNote: "Note:",
    overlayText: "Rescue coordinates are approximate. They do not reflect the exact location of rescues. Contact the rescue organisation to get the actual location.",

    // about.html
    aboutPageTitle: "About FindARescue.ie",
    aboutIntro: "FindARescue.ie is Ireland's most comprehensive database of animal rescue organisations, shelters, and welfare groups. Our mission is to:",
    aboutMissionLine1: "Promote the incredible work of animal rescue organisations across Ireland",
    aboutMissionLine2: "Encourage pet adoption instead of purchasing",
    aboutMissionLine3: "Raise awareness of local wildlife welfare groups",
    aboutGoalLabel: "Our goal:",
    aboutGoalText: "To make it as easy as possible for people to find, support, and connect with animal rescue organisations throughout Ireland.",
    aboutWhoTitle: "Who we are",
    aboutWhoP1: "FindARescue.ie is a private, fully volunteer-led project. We don't receive any funding, grants, or sponsorships. We do not advertise and we do not sell any user data.",
    aboutWhoP2: "I created this website as a passion project after working with many amazing animal rescues and seeing how dedicated and hardworking these organisations are. Even though they work night and day to support animals, they never have enough support or resources.",
    aboutWhoP3: "My goal is to shine a light on these organisations and ensure that the public is aware of the great work that animal rescue groups do. In turn I hope we can get more animals adopted instead of bought, more donations, more volunteers, and more support for animal welfare in general.",
    aboutWhoP4: "NB: FindARescue.ie is not a rescue or charity organisation and we will never reach out asking for donations. Report any suspicious messages immediately.",
    aboutPhotoCaption: "Me and foster dog Toast.",
    aboutForWhoTitle: "Who this website is for",
    aboutForWhoIntro: "This website is helpful for many people, including:",
    aboutForWhoLi1: "Prospective pet adopters or foster homes",
    aboutForWhoLi2: "People looking to surrender an animal",
    aboutForWhoLi3: "Anyone who has encountered injured wildlife",
    aboutForWhoLi4: "Animal rescue donors and volunteers",
    aboutForWhoLi5: "Animal welfare advocates",
    aboutNotForIntro: "We generally do not cover any corporate activity, including:",
    aboutNotForLi1: "Animal breeding, buying, selling",
    aboutNotForLi2: "Pet shops",
    aboutNotForLi3: "Service animals",
    aboutNotForLi4: "Grooming, walking, or training services",
    aboutNotForLi5: "Pest control",
    aboutContactTitle: "Contact us",
    aboutContactIntro: "If you're an animal rescue organisation that's not yet listed on our platform, or if you notice any information that needs updating, please get in touch. We're continuously expanding our database to ensure it remains the most comprehensive resource for animal rescue in Ireland.",
    aboutContactEmail: "Email: info@findarescue.ie", // unused: kept for reuse
    aboutContactInstagram: "Instagram:", // unused: kept for reuse
    aboutContactFeedback: "We'd love to hear your feedback, suggestions, or any questions you might have.",
    aboutSupportTitle: "Support us",
    aboutSupportIntro: "FindARescue.ie is run entirely by myself with no advertising or sponsorship. Keeping the site online does come with a small cost each year:",
    aboutSupportHosting: "Hosting \u2014 \u20ac114.38 / year",
    aboutSupportDomain: "Domain \u2014 \u20ac36.99 / year",
    aboutSupportOutro: "If you'd like to help keep FindARescue.ie running, even a small contribution helps. Every cent goes directly towards running costs \u2014 nothing else.",
    aboutTrackerLabel: "2026 running costs",
    aboutTrackerGoal: "Goal: \u20ac150",
    aboutBmcNote: "No account needed \u2014 any amount is greatly appreciated! \u2615",

    // Redesign (2026) — new UI strings
    skipToResults: "Skip to results",
    skipToContent: "Skip to content",
    heroTitle: "Find an animal rescue near you",
    heroTrust: "Listings aren't vetted or endorsed.",
    searchClear: "Clear search",
    categoriesLabel: "Animal type",
    resultsCount: "{n} organisations",
    resultsCountOne: "1 organisation",
    clearFilters: "Clear filters",
    noResultsTitle: "No rescues match your search",
    noResultsHint: "Try a different word or county, or clear your filters to see every organisation.",
    charityBadgeLabel: "Registered charity",
    inactiveLabel: "May be inactive",
    closeLabel: "Close",
    footerTagline: "A free, volunteer-run directory of animal rescues across all 32 counties.",
    footerValues: "No ads · No sponsorship · No data sold",
    footerExplore: "Explore",
    footerContact: "Contact",
    mapTitle: "Rescue map",
    mapBrowseList: "Browse as a list",
    mapLoading: "Loading map…",
    mapDismiss: "Dismiss",
    aboutBmcButton: "Buy me a coffee",
    aboutRaised: "raised",
  },

  ga: {
    navHome: "Baile",
    navMap: "Léarscáil",
    navAbout: "Fúinn",
    langToggleAriaEn: "Athraigh go Gaeilge",
    langToggleAriaGa: "Athraigh go Béarla",

    homeTagline: "An bunachar sonraí tarrthála ainmhithe is cuimsithí in Éirinn.",
    homeFeedback: "Aiseolas agus fiosruithe: info@findarescue.ie", // unused: kept for reuse
    safetyLinkText: "Sábháilteacht ar líne",

    safetyModalTitle: "Beartas Sábháilteachta ar Líne",
    safetyModalP1: "Cuidíonn FindARescue.ie le daoine a nascadh le heagraíochtaí tarrthála agus athlonnaithe ainmhithe. Ní dhéanaimid grinnfhiosrú, formhuiniú, ná fíorú ar na heagraíochtaí atá liostaithe ar an suíomh gréasáin seo.",
    safetyModalP2: "Sula ndéanann tú síntiús, uchtú, altram, nó eolas pearsanta a roinnt, molaimid duit:",
    safetyModalLi1: "Taighde a dhéanamh ar an eagraíocht go neamhspleách.",
    safetyModalLi2: "Suíomh gréasáin, láithreacht ar na meáin shóisialta, agus sonraí teagmhála a sheiceáil.",
    safetyModalLi3: "Má mhaíonn an eagraíocht gur carthanacht chláraithe í, fíoraigh a hUimhir Charthanas Cláraithe (UCC) ar chlár poiblí an Rialálaí Carthanas.",
    safetyModalLi4: "Ceisteanna a chur faoi stair, sláinte, agus cúram an ainmhí.",
    safetyModalLi5: "Taifid tréidliachta nó páipéarachas uchtála a iarraidh nuair is cuí.",
    safetyModalLi6: "Cuairt a thabhairt ar an eagraíocht tarrthála nó físghlaoch a shocrú más féidir.",
    safetyModalLi7: "Bí airdeallach faoi iarratais ar mhodhanna íocaíochta neamhghnácha nó ar íocaíochtaí práinneacha.",
    safetyModalLi8: "Ná roinn eolas íogair airgeadais nó pearsanta go deo mura bhfuil sé riachtanach agus go bhfuil tú cinnte go bhfuil an eagraíocht dlisteanach.",
    safetyModalP3: "Má cheapann tú go bhfuil rud éigin amhrasach, bíodh muinín agat as do bhreithiúnas féin agus cuir stad leis an teagmháil.",
    safetyModalP4: "Níl FindARescue.ie freagrach as idirbhearta, comhaontuithe, cumarsáid, ná díospóidí idir úsáideoirí agus eagraíochtaí atá liostaithe ar an suíomh.",

    searchPlaceholder: "Cuardaigh tarrthálacha...",
    searchInfoTooltip: "Clóscríobh eochairfhocail chun eagraíochtaí ar leith a aimsiú. Is féidir leat scagadh a dhéanamh de réir catagóire nó suímh freisin.", // unused: kept for reuse
    filterLocationDefault: "Scag de réir Suímh",
    filterAllLocations: "Gach suíomh", // unused: kept for reuse
    // TODO (ga): not yet translated — mirrors English as a placeholder, same as the rest of this file
    locationClearAll: "Clear all",
    locationDone: "Done",
    locationRemove: "Remove",
    resetTooltip: "Athshocraigh na scagairí.", // unused: kept for reuse
    noResults: "Níor aimsíodh aon acmhainní. Bain triail as na roghanna scagtha a athshocrú tríd an \ud83d\udd04 a chliceáil.", // unused: kept for reuse
    websiteLink: "Suíomh Gréasáin",
    linkUnavailable: "not available", // TODO (ga)
    facebookLink: "Facebook",
    instagramLink: "Instagram",
    charityBadgeTooltip: "Uimhir Charthanas Cláraithe: ",

    catAll: "Uile",
    catDogs: "Madraí",
    catCats: "Cait",
    catBirds: "Éin",
    catSmallAnimals: "Ainmhithe Beaga",
    catEquines: "Capaill",
    catWildlife: "Fiadhúlra",

    locConnacht: "Connachta",
    locLeinster: "Laighin",
    locMunster: "An Mhumhain",
    locUlster: "Ulaidh",
    locAntrim: "Aontroim",
    locArmagh: "Ard Mhacha",
    locCarlow: "Ceatharlach",
    locCavan: "An Cabhán",
    locClare: "An Clár",
    locCork: "Corcaigh",
    locDerry: "Doire",
    locDonegal: "Dún na nGall",
    locDown: "An Dún",
    locDublin: "Baile Átha Cliath",
    locFermanagh: "Fear Manach",
    locGalway: "Gaillimh",
    locKerry: "Ciarraí",
    locKildare: "Cill Dara",
    locKilkenny: "Cill Chainnigh",
    locLaois: "Laois",
    locLeitrim: "Liatroim",
    locLimerick: "Luimneach",
    locLongford: "An Longfort",
    locLouth: "Lú",
    locMayo: "Maigh Eo",
    locMeath: "An Mhí",
    locMonaghan: "Muineachán",
    locOffaly: "Uíbh Fhailí",
    locRoscommon: "Ros Comáin",
    locSligo: "Sligeach",
    locTipperary: "Tiobraid Árann",
    locTyrone: "Tír Eoghain",
    locWaterford: "Port Láirge",
    locWestmeath: "An Iarmhí",
    locWexford: "Loch Garman",
    locWicklow: "Cill Mhantáin",

    footerCopyright: "Cóipcheart FindARescue.ie 2026",

    overlayNote: "Nóta:",
    overlayText: "Níl comhordanáidí na dtarrthálacha ach neasach. Ní léiríonn siad suíomh cruinn na dtarrthálacha. Déan teagmháil leis an eagraíocht tarrthála chun an suíomh iarbhír a fháil.",

    aboutPageTitle: "Maidir le FindARescue.ie",
    aboutIntro: "Is é FindARescue.ie an bunachar sonraí is cuimsithí in Éirinn d'eagraíochtaí tarrthála ainmhithe, tearmainn, agus grúpaí leasa ainmhithe. Is é ár misean:",
    aboutMissionLine1: "Obair iontach na n-eagraíochtaí tarrthála ainmhithe ar fud na hÉireann a chur chun cinn",
    aboutMissionLine2: "Uchtú peataí a spreagadh in ionad iad a cheannach",
    aboutMissionLine3: "Feasacht a ardú faoi ghrúpaí leasa fiadhúlra áitiúla",
    aboutGoalLabel: "Ár Sprioc:",
    aboutGoalText: "É a dhéanamh chomh héasca agus is féidir do dhaoine eagraíochtaí tarrthála ainmhithe ar fud na hÉireann a aimsiú, tacú leo, agus nascadh leo.",
    aboutWhoTitle: "Cé muid",
    aboutWhoP1: "Is tionscadal príobháideach é FindARescue.ie atá á stiúradh go hiomlán ag oibrithe deonacha. Ní fhaighimid maoiniú, deontais, ná urraíocht ar bith. Ní dhéanaimid fógraíocht agus ní dhíolaimid aon sonraí úsáideoirí.",
    aboutWhoP2: "Chruthaigh mé an suíomh gréasáin seo mar thionscadal paisin tar éis dom a bheith ag obair le go leor eagraíochtaí tarrthála ainmhithe iontacha agus mé ag feiceáil cé chomh tiomanta agus dícheallach is atá na heagraíochtaí seo. Cé go n-oibríonn siad de lá is d'oíche chun ainmhithe a chothú, ní bhíonn dóthain tacaíochta ná acmhainní acu riamh.",
    aboutWhoP3: "Is é mo sprioc solas a chaitheamh ar na heagraíochtaí seo agus a chinntiú go bhfuil an pobal ar an eolas faoin obair iontach a dhéanann grúpaí tarrthála ainmhithe. Mar thoradh air sin, tá súil agam gur féidir linn níos mó ainmhithe a fháil uchtaithe in ionad ceannaithe, níos mó síntiús, níos mó oibrithe deonacha, agus níos mó tacaíochta do leas ainmhithe i gcoitinne.",
    aboutWhoP4: "Nóta: Ní eagraíocht tarrthála ná carthanachta é FindARescue.ie agus ní iarrfaimid síntiúis ort go deo. Tuairiscigh aon teachtaireachtaí amhrasacha láithreach.",
    aboutPhotoCaption: "Mise agus madra altrama, Toast.",
    aboutForWhoTitle: "Cé dó a bhfuil an suíomh gréasáin seo",
    aboutForWhoIntro: "Tá an suíomh gréasáin seo úsáideach do go leor daoine, lena n-áirítear:",
    aboutForWhoLi1: "Uchtaitheoirí peataí ionchasacha nó tithe altrama",
    aboutForWhoLi2: "Daoine atá ag iarraidh ainmhí a thabhairt isteach",
    aboutForWhoLi3: "Aon duine a bhfuil fiadhúlra gortaithe casta air",
    aboutForWhoLi4: "Deontóirí agus oibrithe deonacha tarrthála ainmhithe",
    aboutForWhoLi5: "Abhcóidí leasa ainmhithe",
    aboutNotForIntro: "Go ginearálta, ní chlúdaímid aon ghníomhaíocht chorparáideach, lena n-áirítear:",
    aboutNotForLi1: "Póraíocht, ceannach, díolachán ainmhithe",
    aboutNotForLi2: "Siopaí peataí",
    aboutNotForLi3: "Ainmhithe seirbhíse",
    aboutNotForLi4: "Seirbhísí grúmaeireachta, siúlóide, nó oiliúna",
    aboutNotForLi5: "Srianadh lotnaidí",
    aboutContactTitle: "Déan teagmháil linn",
    aboutContactIntro: "Más eagraíocht tarrthála ainmhithe thú nach bhfuil liostaithe ar ár n-ardán fós, nó má thugann tú faoi deara aon eolas atá le nuashonrú, déan teagmháil linn le do thoil. Táimid ag leathnú ár mbunachair sonraí i gcónaí lena chinntiú go bhfanann sé mar an acmhainn is cuimsithí do tharrtháil ainmhithe in Éirinn.",
    aboutContactEmail: "Ríomhphost: info@findarescue.ie", // unused: kept for reuse
    aboutContactInstagram: "Instagram:", // unused: kept for reuse
    aboutContactFeedback: "Ba bhreá linn cloisteáil faoi d'aiseolas, do mholtaí, nó aon cheisteanna atá agat.",
    aboutSupportTitle: "Tacaigh linn",
    aboutSupportIntro: "Reáchtálaim FindARescue.ie go hiomlán liom féin gan fógraíocht ná urraíocht ar bith. Bíonn costas beag ag baint le coinneáil an tsuímh ar líne gach bliain:",
    aboutSupportHosting: "Óstáil \u2014 \u20ac114.38 / bliain",
    aboutSupportDomain: "Fearann \u2014 \u20ac36.99 / bliain",
    aboutSupportOutro: "Más mian leat cuidiú le FindARescue.ie a choinneáil ag rith, cuidíonn fiú síntiús beag. Téann gach cent go díreach chuig costais reatha \u2014 gan aon rud eile.",
    aboutTrackerLabel: "Costais reatha 2026",
    aboutTrackerGoal: "Sprioc: \u20ac150",
    aboutBmcNote: "Níl cuntas ag teastáil \u2014 táimid buíoch as aon mhéid! \u2615",

    // TODO (ga): redesign (2026) strings — not yet translated, English placeholders
    skipToResults: "Skip to results", // TODO (ga)
    skipToContent: "Skip to content", // TODO (ga)
    heroTitle: "Find an animal rescue near you", // TODO (ga)
    heroTrust: "Listings aren't vetted or endorsed.", // TODO (ga)
    searchClear: "Clear search", // TODO (ga)
    categoriesLabel: "Animal type", // TODO (ga)
    resultsCount: "{n} organisations", // TODO (ga)
    resultsCountOne: "1 organisation", // TODO (ga)
    clearFilters: "Clear filters", // TODO (ga)
    noResultsTitle: "No rescues match your search", // TODO (ga)
    noResultsHint: "Try a different word or county, or clear your filters to see every organisation.", // TODO (ga)
    charityBadgeLabel: "Registered charity", // TODO (ga)
    inactiveLabel: "May be inactive", // TODO (ga)
    closeLabel: "Close", // TODO (ga)
    footerTagline: "A free, volunteer-run directory of animal rescues across all 32 counties.", // TODO (ga)
    footerValues: "No ads · No sponsorship · No data sold", // TODO (ga)
    footerExplore: "Explore", // TODO (ga)
    footerContact: "Contact", // TODO (ga)
    mapTitle: "Rescue map", // TODO (ga)
    mapBrowseList: "Browse as a list", // TODO (ga)
    mapLoading: "Loading map…", // TODO (ga)
    mapDismiss: "Dismiss", // TODO (ga)
    aboutBmcButton: "Buy me a coffee", // TODO (ga)
    aboutRaised: "raised", // TODO (ga)
  },
};

// ==========================================================================
// Language state + DOM application
// ==========================================================================
const FAR_LANG_KEY = "far_lang";

// One-time: if the URL specifies a language, seed localStorage with it.
// Runs once at script load — does NOT re-check the URL on every getLang()
// call, so it won't fight with the toggle button afterward.
// localStorage can throw (blocked cookies, some private modes). If it does,
// the language is kept in memory for this page view instead of breaking
// the whole site.
let memoryLang = "en";
function readStoredLang() {
  try { return localStorage.getItem(FAR_LANG_KEY); } catch (e) { return memoryLang; }
}
function storeLang(lang) {
  memoryLang = lang;
  try { localStorage.setItem(FAR_LANG_KEY, lang); } catch (e) { /* in-memory only */ }
}

(function initLangFromUrl() {
  const urlLang = new URLSearchParams(window.location.search).get("lang");
  if (urlLang === "ga" || urlLang === "en") storeLang(urlLang);
})();

function getLang() {
  return readStoredLang() === "ga" ? "ga" : "en";
}

function setLang(lang) {
  storeLang(lang);
  applyTranslations();
}

function toggleLang() {
  setLang(getLang() === "en" ? "ga" : "en");
}

function t(key) {
  const lang = getLang();
  return (translations[lang] && translations[lang][key]) || translations.en[key] || key;
}

function applyTranslations() {
  const lang = getLang();
  document.documentElement.lang = lang;

  // Plain text content
  document.querySelectorAll("[data-i18n]").forEach((el) => {
    el.textContent = t(el.getAttribute("data-i18n"));
  });

  // Placeholders (inputs)
  document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
    el.placeholder = t(el.getAttribute("data-i18n-placeholder"));
  });

  // aria-label attributes (icon-only buttons, groups)
  document.querySelectorAll("[data-i18n-aria]").forEach((el) => {
    el.setAttribute("aria-label", t(el.getAttribute("data-i18n-aria")));
  });

  // Logo swap — elements tagged with data-src-en / data-src-ga. If the Irish
  // file is missing (not uploaded yet), fall back to the English one.
  document.querySelectorAll("[data-src-en]").forEach((img) => {
    const gaSrc = img.getAttribute("data-src-ga");
    const enSrc = img.getAttribute("data-src-en");
    img.onerror = () => { img.onerror = null; img.src = enSrc; };
    img.src = lang === "ga" && gaSrc ? gaSrc : enSrc;
  });

  // Toggle switch — updates the sliding thumb, the "English"/"Gaeilge" label,
  // and accessibility state (role="switch" aria-checked)
  document.querySelectorAll(".lang-switch").forEach((btn) => {
    const label = btn.querySelector(".lang-switch-label");
    if (label) label.textContent = lang === "ga" ? "Gaeilge" : "English";
    btn.setAttribute("aria-checked", lang === "ga" ? "true" : "false");
    btn.setAttribute(
      "aria-label",
      lang === "en" ? t("langToggleAriaEn") : t("langToggleAriaGa")
    );
    btn.classList.toggle("is-ga", lang === "ga");
  });

  // Re-render the directory's dynamic parts if this page has them
  // (index.html only — defined in app.js).
  if (typeof renderCategoryFilter === "function") renderCategoryFilter();
  if (typeof renderLocationFilter === "function") renderLocationFilter();
  if (typeof renderResources === "function") renderResources();
}

document.addEventListener("DOMContentLoaded", applyTranslations);