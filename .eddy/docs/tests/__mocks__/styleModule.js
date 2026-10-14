// Stands in for a CSS module, returning each class name as written.
module.exports = new Proxy({}, { get: (target, name) => name });
