const { withAndroidManifest } = require('expo/config-plugins');
module.exports = (config, { allowHttp = false } = {}) => withAndroidManifest(config, mod => {
  const app = mod.modResults.manifest.application[0];
  app.$['android:usesCleartextTraffic'] = allowHttp ? 'true' : 'false';
  return mod;
});
