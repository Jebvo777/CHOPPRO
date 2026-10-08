import type { ExpoConfig } from 'expo/config';
const config: ExpoConfig = {
  name: 'ЧОППРО', slug: 'choppro', version: '3.0.0', scheme: 'choppro', orientation: 'portrait',
  icon: './assets/icon.png', userInterfaceStyle: 'light',
  ios: { bundleIdentifier: 'ru.choppro.guard', supportsTablet: true, infoPlist: { NSCameraUsageDescription: 'Камера нужна для QR-кодов и фотографий происшествий.', NSLocationWhenInUseUsageDescription: 'Местоположение фиксируется только при отметке смены или контрольной точки.', NSMicrophoneUsageDescription: 'Микрофон нужен для записи объяснения происшествия.', NSAppTransportSecurity: { NSAllowsArbitraryLoads: process.env.CHOPPRO_ALLOW_HTTP === '1' } } },
  android: { package: 'ru.choppro.guard', versionCode: 30000, permissions: ['CAMERA', 'ACCESS_COARSE_LOCATION', 'ACCESS_FINE_LOCATION', 'RECORD_AUDIO'], blockedPermissions: ['ACCESS_BACKGROUND_LOCATION'] },
  plugins: [ ['./plugins/network.cjs', { allowHttp: process.env.CHOPPRO_ALLOW_HTTP === '1' }], ['expo-camera', { cameraPermission: 'Разрешить сканирование QR и съемку происшествий?', recordAudioAndroid: false }], ['expo-location', { locationWhenInUsePermission: 'Разрешить определение местоположения при рабочих действиях?' }], ['expo-image-picker', { photosPermission: 'Разрешить выбрать фотографию происшествия?', cameraPermission: 'Разрешить съемку фотографии происшествия?', microphonePermission: false }], ['expo-audio', { microphonePermission: 'Разрешить записать аудио происшествия?' }], ['expo-sqlite', { useSQLCipher: true }], 'expo-secure-store', 'expo-document-picker', 'expo-sharing' ],
  web: { bundler: 'metro', output: 'single', favicon: './assets/icon.png' },
  extra: { apiUrl: process.env.EXPO_PUBLIC_API_URL ?? '', integrations: false }
};
export default config;
