// Expo-managed workflow Babel config. `babel-preset-expo` already includes
// react-native, TypeScript, and JSX support. `expo-router/babel` is a no-op
// on SDK 50+ (folded into the main preset) but is safe to keep for docs.
module.exports = function (api) {
  api.cache(true);
  return {
    presets: ['babel-preset-expo'],
    plugins: [
      // Required for react-native-reanimated. MUST be listed last.
      'react-native-reanimated/plugin',
    ],
  };
};
