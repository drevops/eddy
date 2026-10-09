const fs = require('fs');
const path = require('path');

// Discover js/ directories in custom modules and themes.
const dirs = ['web/modules/custom', 'web/themes/custom'];
const roots = [];

dirs.forEach((dir) => {
  if (fs.existsSync(dir)) {
    fs.readdirSync(dir).forEach((name) => {
      const jsDir = path.resolve(dir, name, 'js');
      if (fs.existsSync(jsDir)) {
        roots.push(jsDir);
      }
    });
  }
});

module.exports = {
  // V8 coverage tracks only files inside rootDir, and the sources load from
  // their real paths outside the build directory, so anchor at the project
  // root rather than the build directory.
  rootDir: path.resolve(__dirname, '..'),
  testEnvironment: 'jsdom',
  roots,
  testMatch: ['**/*.test.js'],
  testPathIgnorePatterns: ['/node_modules/', '/vendor/'],
  collectCoverage: true,
  coverageProvider: 'v8',
  coveragePathIgnorePatterns: ['/node_modules/', '/vendor/', '\\.test\\.js$'],
  coverageDirectory: path.resolve(__dirname, '../.logs/coverage/jest'),
  coverageReporters: [
    'text',
    ['html', { subdir: '.coverage-html' }],
    ['cobertura', { file: 'cobertura.xml' }],
  ],
  modulePaths: [path.resolve(__dirname, 'node_modules')],
};
