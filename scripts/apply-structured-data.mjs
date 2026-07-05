import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();
const BASE = "https://www.renoveerikodu.ee";

const SERVICE_PAGES = {
  "plaatimine.html": ["Plaatimine Tallinnas ja Harjumaal", "Plaatimistööd vannitubades, köökides ja teistes ruumides Tallinnas ja Harjumaal."],
  "maalritood.html": ["Maalritööd Tallinnas ja Harjumaal", "Maalritööd, pahteldus, kruntimine ja siseviimistlus Tallinnas ja Harjumaal."],
  "ledlahendused.html": ["LED lahendused Tallinnas ja Harjumaal", "LED valgustuse ja dekoratiivsete valguslahenduste paigaldus Tallinnas ja Harjumaal."],
  "seinapaneelidepaigaldus.html": ["Seinapaneelide paigaldus Tallinnas ja Harjumaal", "Dekoratiivsete ja akustiliste seinapaneelide paigaldus Tallinnas ja Harjumaal."],
  "parketipaigaldus.html": ["Parketi paigaldus Tallinnas ja Harjumaal", "Parketi ja laminaatpõranda paigaldus koos aluspinna ettevalmistusega Tallinnas ja Harjumaal."],
  "tapeetimine.html": ["Tapeetimine Tallinnas ja Harjumaal", "Tapeedi paigaldus ja seinte ettevalmistus Tallinnas ja Harjumaal."],
  "kipsitood.html": ["Kipsitööd Tallinnas ja Harjumaal", "Kipsplaadi, vaheseinte, lagede ja siseehituse tööd Tallinnas ja Harjumaal."],
  "lammutustood.html": ["Lammutustööd Tallinnas ja Harjumaal", "Siseruumide lammutustööd, demontaaž ja ettevalmistus renoveerimiseks Tallinnas ja Harjumaal."],
  "tellingutepaigaldus.html": ["Tellingute paigaldus Tallinnas ja Harjumaal", "Tellingute paigaldus ja tööplatvormide ettevalmistus ehitus- ja fassaaditöödeks."],
  "betoonitood.html": ["Betoonitööd Tallinnas ja Harjumaal", "Betoonitööd, aluspinna ettevalmistus ja väiksemad betoonkonstruktsioonid Tallinnas ja Harjumaal."],
  "vundamenditood.html": ["Vundamenditööd Tallinnas ja Harjumaal", "Vundamendi ehituse, paranduse ja hüdroisolatsiooniga seotud tööd Tallinnas ja Harjumaal."],
  "fassaaditood.html": ["Fassaaditööd Tallinnas ja Harjumaal", "Fassaadi renoveerimine, parandused, pesu, kruntimine ja värvimine Tallinnas ja Harjumaal."],
  "fassaadivarvimine.html": ["Fassaadi värvimine Tallinnas ja Harjumaal", "Puit- ja krohvfassaadide värvimine ning ettevalmistus Tallinnas ja Harjumaal."],
  "katusetood.html": ["Katusetööd Tallinnas ja Harjumaal", "Katuse hooldus, pesu, parandused ja renoveerimistööd Tallinnas ja Harjumaal."],
  "lumetood.html": ["Lumetööd Tallinnas ja Harjumaal", "Katuste, hoovide ja hoonete ümbruse lumekoristus Tallinnas ja Harjumaal."]
};

const PROJECT_PAGES = {
  "vannitoa-plaatimine-tallinn.html": "Vannitoa plaatimine Tallinnas",
  "parketi-paigaldus-tallinn-korter.html": "Parketi paigaldus Tallinnas korteris",
  "fassaadi-varvimine-tallinn-eramu.html": "Fassaadi värvimine Tallinnas eramul",
  "katuse-pesu-ja-hooldus-tallinn.html": "Katuse pesu ja hooldus Tallinnas",
  "led-valgustuse-paigaldus-tallinn.html": "LED valgustuse paigaldus Tallinnas"
};

function stripTags(value = "") {
  return value.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
}

function getAttr(tag, name) {
  const pattern = new RegExp(`${name}=["']([^"']*)["']`, "i");
  const match = tag.match(pattern);
  return match ? match[1] : "";
}

function absUrl(url) {
  if (!url) return null;
  if (/^https?:\/\//.test(url)) return url;
  if (url.startsWith("/")) return BASE + url;
  return `${BASE}/${url}`;
}

function pageUrl(file) {
  return file === "index.html" ? `${BASE}/` : `${BASE}/${file.replace(/\.html$/, "")}`;
}

function parseHtml(html) {
  const title = stripTags((html.match(/<title[^>]*>([\s\S]*?)<\/title>/i) || [])[1] || "");
  const h1 = stripTags((html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i) || [])[1] || "");
  const metaTag = (html.match(/<meta[^>]+name=["']description["'][^>]*>/i) || [])[0] || "";
  const description = getAttr(metaTag, "content");
  const images = [];
  for (const match of html.matchAll(/<img\b[^>]*>/gi)) {
    const src = getAttr(match[0], "src");
    if (src) images.push({ src, alt: getAttr(match[0], "alt") });
  }
  const details = [];
  for (const match of html.matchAll(/<details[^>]*>([\s\S]*?)<\/details>/gi)) {
    const body = match[1];
    const question = stripTags((body.match(/<summary[^>]*>([\s\S]*?)<\/summary>/i) || [])[1] || "");
    const answer = stripTags(body.replace(/<summary[\s\S]*?<\/summary>/i, ""));
    if (question && answer) details.push({ question, answer });
  }
  return { title, h1, description, images, details };
}

function organization() {
  return {
    "@type": "Organization",
    "@id": `${BASE}/#organization`,
    name: "RK Meistrid OÜ",
    alternateName: "Renoveeri Kodu",
    url: `${BASE}/`,
    logo: `${BASE}/images/RKMeistrid_logo.png`,
    email: "info@renoveerikodu.ee",
    telephone: "+37255515783",
    identifier: "16541086"
  };
}

function localBusiness() {
  return {
    "@type": ["LocalBusiness", "HomeAndConstructionBusiness"],
    "@id": `${BASE}/#localbusiness`,
    name: "Renoveeri Kodu",
    legalName: "RK Meistrid OÜ",
    url: `${BASE}/`,
    image: `${BASE}/images/RKMeistrid_logo.png`,
    email: "info@renoveerikodu.ee",
    telephone: "+37255515783",
    priceRange: "€€",
    areaServed: [
      { "@type": "AdministrativeArea", name: "Tallinn" },
      { "@type": "AdministrativeArea", name: "Harjumaa" }
    ],
    address: { "@type": "PostalAddress", addressCountry: "EE" },
    parentOrganization: { "@id": `${BASE}/#organization` },
    description: "RK Meistrid OÜ ehk Renoveeri Kodu pakub renoveerimis-, siseviimistlus-, plaatimis-, fassaadi-, katuse- ja ehitustöid Tallinnas ning Harjumaal."
  };
}

function website() {
  return {
    "@type": "WebSite",
    "@id": `${BASE}/#website`,
    url: `${BASE}/`,
    name: "Renoveeri Kodu",
    publisher: { "@id": `${BASE}/#organization` },
    inLanguage: "et"
  };
}

function webpage(url, name, description) {
  return {
    "@type": "WebPage",
    "@id": `${url}#webpage`,
    url,
    name,
    description,
    isPartOf: { "@id": `${BASE}/#website` },
    about: { "@id": `${BASE}/#localbusiness` },
    inLanguage: "et"
  };
}

function breadcrumb(url, name, file) {
  const itemListElement = [
    { "@type": "ListItem", position: 1, name: "Avaleht", item: `${BASE}/` }
  ];
  if (file !== "index.html") {
    itemListElement.push({ "@type": "ListItem", position: 2, name, item: url });
  }
  return { "@type": "BreadcrumbList", "@id": `${url}#breadcrumb`, itemListElement };
}

function imageObjects(url, images) {
  const seen = new Set();
  const nodes = [];
  for (const image of images.slice(0, 8)) {
    const imageUrl = absUrl(image.src);
    if (!imageUrl || seen.has(imageUrl)) continue;
    seen.add(imageUrl);
    nodes.push({
      "@type": "ImageObject",
      "@id": `${url}#image-${nodes.length + 1}`,
      url: imageUrl,
      contentUrl: imageUrl,
      caption: image.alt || ""
    });
  }
  return nodes;
}

function faq(url, details) {
  if (!details.length) return null;
  return {
    "@type": "FAQPage",
    "@id": `${url}#faq`,
    mainEntity: details.map((item) => ({
      "@type": "Question",
      name: item.question,
      acceptedAnswer: { "@type": "Answer", text: item.answer }
    }))
  };
}

function service(url, name, description, images) {
  const node = {
    "@type": "Service",
    "@id": `${url}#service`,
    name,
    description,
    provider: { "@id": `${BASE}/#localbusiness` },
    areaServed: ["Tallinn", "Harjumaa"],
    serviceType: name,
    url
  };
  if (images[0]) node.image = absUrl(images[0].src);
  return node;
}

function article(url, headline, description, images) {
  const node = {
    "@type": "Article",
    "@id": `${url}#article`,
    headline,
    description,
    url,
    mainEntityOfPage: { "@id": `${url}#webpage` },
    publisher: { "@id": `${BASE}/#organization` },
    author: { "@id": `${BASE}/kontakt#hans-suurvali` },
    inLanguage: "et"
  };
  if (images[0]) node.image = [absUrl(images[0].src)];
  return node;
}

function people() {
  return [
    { "@type": "Person", "@id": `${BASE}/kontakt#hans-suurvali`, name: "Hans Suurväli", jobTitle: "Juhatuse liige / Projektijuht", email: "hans@renoveerikodu.ee", worksFor: { "@id": `${BASE}/#organization` } },
    { "@type": "Person", "@id": `${BASE}/kontakt#hannes-suurvali`, name: "Hannes Suurväli", jobTitle: "Turundusspetsialist", email: "info@renoveerikodu.ee", worksFor: { "@id": `${BASE}/#organization` } },
    { "@type": "Person", "@id": `${BASE}/kontakt#eleri-lipping`, name: "Eleri Lipping", jobTitle: "Müügijuht", email: "eleri@renoveerikodu.ee", worksFor: { "@id": `${BASE}/#organization` } }
  ];
}

function offerCatalog() {
  const offers = [
    ["Kipsplaadi paigaldus", "16", "m2"],
    ["Pahteldus ja lihvimine", "9", "m2"],
    ["Kruntimine", "3.5", "m2"],
    ["Värvimine, 2 kihti", "7.5", "m2"],
    ["Hüdroisolatsiooni paigaldus", "14", "m2"],
    ["Seinte ja põranda plaatimine", "49", "m2"],
    ["Laudparketi paigaldus", "12", "m2"],
    ["Laminaatparketi paigaldus", "9", "m2"],
    ["Fassaadi pesu või puhastus", "6", "m2"]
  ];
  return {
    "@type": "OfferCatalog",
    "@id": `${BASE}/hinnakiri#offercatalog`,
    name: "Renoveerimistööde hinnakiri Tallinnas ja Harjumaal",
    url: `${BASE}/hinnakiri`,
    provider: { "@id": `${BASE}/#localbusiness` },
    areaServed: ["Tallinn", "Harjumaa"],
    itemListElement: offers.map(([name, price, unitText]) => ({
      "@type": "Offer",
      name,
      priceSpecification: { "@type": "UnitPriceSpecification", priceCurrency: "EUR", price, unitText, description: "Hind alates" }
    }))
  };
}

function portfolioCollection() {
  return [
    {
      "@type": "CollectionPage",
      "@id": `${BASE}/tehtudtood#collection`,
      name: "Tehtud renoveerimistööd Tallinnas ja Harjumaal",
      url: `${BASE}/tehtudtood`,
      description: "Renoveeri Kodu portfoolio tehtud siseviimistlus-, plaatimis-, parketi-, fassaadi-, katuse- ja renoveerimistöödest.",
      isPartOf: { "@id": `${BASE}/#website` }
    },
    {
      "@type": "ItemList",
      "@id": `${BASE}/tehtudtood#projects`,
      name: "Renoveeri Kodu projektinäited",
      itemListElement: Object.entries(PROJECT_PAGES).map(([file, name], index) => ({
        "@type": "ListItem",
        position: index + 1,
        url: pageUrl(file),
        name
      }))
    }
  ];
}

function cleanExistingSchema(html) {
  return html.replace(/\s*<script[^>]+type=["']application\/ld\+json["'][^>]*>[\s\S]*?<\/script>\s*/gi, "\n");
}

let count = 0;
for (const file of fs.readdirSync(ROOT).filter((name) => name.endsWith(".html")).sort()) {
  const filePath = path.join(ROOT, file);
  const html = fs.readFileSync(filePath, "utf8");
  const parsed = parseHtml(html);
  const url = pageUrl(file);
  const name = parsed.h1 || parsed.title || file.replace(/\.html$/, "");
  const description = parsed.description || name;
  const graph = [];

  if (file === "index.html") graph.push(organization(), localBusiness(), website());

  graph.push(webpage(url, parsed.title || name, description));
  graph.push(breadcrumb(url, name, file));

  if (file === "kontakt.html") {
    graph.push(localBusiness(), ...people());
  } else if (SERVICE_PAGES[file]) {
    const [serviceName, serviceDescription] = SERVICE_PAGES[file];
    graph.push(service(url, serviceName, serviceDescription, parsed.images));
    const faqNode = faq(url, parsed.details);
    if (faqNode) graph.push(faqNode);
  } else if (PROJECT_PAGES[file]) {
    graph.push(article(url, PROJECT_PAGES[file], description, parsed.images));
    const faqNode = faq(url, parsed.details);
    if (faqNode) graph.push(faqNode);
  } else if (file === "hinnakiri.html") {
    graph.push(offerCatalog());
    const faqNode = faq(url, parsed.details);
    if (faqNode) graph.push(faqNode);
  } else if (file === "tehtudtood.html") {
    graph.push(...portfolioCollection());
    const faqNode = faq(url, parsed.details);
    if (faqNode) graph.push(faqNode);
  }

  graph.push(...imageObjects(url, parsed.images));

  const ids = graph.map((node) => node["@id"]).filter(Boolean);
  if (ids.length !== new Set(ids).size) throw new Error(`Duplicate @id in ${file}`);

  const schema = { "@context": "https://schema.org", "@graph": graph };
  JSON.parse(JSON.stringify(schema));

  const script = `<script type="application/ld+json">\n${JSON.stringify(schema, null, 2)}\n</script>\n`;
  const cleaned = cleanExistingSchema(html);
  if (!cleaned.includes("</head>")) throw new Error(`${file} missing </head>`);
  fs.writeFileSync(filePath, cleaned.replace("</head>", `${script}</head>`), "utf8");
  count += 1;
}

console.log(`Generated schema for ${count} HTML files`);
