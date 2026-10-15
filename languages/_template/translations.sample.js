// An example of the translations file the portal reads (the file lives on the
// project's own website; this copy only shows the shape it expects).
//
// - One block per language, each holding  key: "text",  one entry per line.
// - The base language block (en) is the source of truth: its keys define what
//   needs translating, and "// Comments" inside it become the section names.
// - A target value that is still the base text, marked with the TODO comment,
//   counts as "not yet translated".
const translations = {
  en: {
    // Navigation
    navHome: "Home",
    navAbout: "About",

    // Home page
    homeTitle: "Welcome to our site",
    homeIntro: "Find what you need, fast.",
  },
  xx: {
    // Navigation
    navHome: "Xhome",
    navAbout: "Xabout",

    // TODO (xx): not yet translated
    homeTitle: "Welcome to our site",
    homeIntro: "Find what you need, fast.", // TODO (xx)
  },
};
