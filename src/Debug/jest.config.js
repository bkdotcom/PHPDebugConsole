// jest.config.js
export default {
  testEnvironment: "jsdom",
  transform: {
    '^.+\\.(js|jsx|ts|tsx)$': 'babel-jest', // or 'ts-jest' if using TypeScript
  },
};
