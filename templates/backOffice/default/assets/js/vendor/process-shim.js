//Remplace le polyfill node "/node/process.mjs" attendu par le bundle esm.sh
//de react-querybuilder (import de side-effect : seul le global compte).
const processShim = globalThis.process ?? (globalThis.process = { env: { NODE_ENV: 'production' } });

export default processShim;
