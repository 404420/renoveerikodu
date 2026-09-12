// npm ci && npm run build:css; generated CSS is committed for PHP/static hosting.
const fs = require('node:fs');
const CleanCSS = require('clean-css');
for (const name of ['main', 'kontakt', 'performance']) {
  const result = new CleanCSS({level: 1, rebase: false}).minify(fs.readFileSync(`assets/css/${name}.css`, 'utf8'));
  if (result.errors.length) throw new Error(result.errors.join('\n'));
  fs.writeFileSync(`assets/css/${name}.min.css`, result.styles);
}
