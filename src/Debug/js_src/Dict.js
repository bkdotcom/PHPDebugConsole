export function Dict (strings) {
  this.strings = strings
}

Dict.prototype.get = function (key) {
  return this.strings[key]
      ? this.strings[key]
      : '{' + key + '}'
}

Dict.prototype.replaceTokens = function (str) {
  var self = this
  return str.replace(/\{string:([^}]*)\}/g, function (match, p1) {
    return self.get(p1)
  })
}

Dict.prototype.update = function (strings) {
  this.strings = Object.assign(this.strings, strings)
}
