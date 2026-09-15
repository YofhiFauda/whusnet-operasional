const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');
const m = src.match(/<script>([\s\S]*)<\/script>/);
let js = m[1];
js = js.replace(/@json\([^)]*\)/g, '[]');
js = js.replace(/\{\{[^}]*\}\}/g, 'X');
fs.writeFileSync(process.argv[3], js);
console.log('extracted', js.length, 'bytes');
