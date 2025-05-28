import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  const setValue = function (el, key, value) {
    let isStringable = true
    let stringified = null
    removeDataHelper(el, key) // remove existing key (whether dataset or special property)
    if (['function', 'undefined'].includes(typeof value)) {
      isStringable = false
    } else if (typeof value === 'object' && value !== null) {
      // object (or array) value
      // store object & array values in special element property (regardless of serializability)
      // this allows user to maintain a reference to the stored value
      isStringable = false
    }
    if (isStringable) {
      try {
        stringified = typeof value === 'string'
          ? value
          : JSON.stringify(value)
      } catch (e) {
      }
      isStringable = typeof stringified === 'string'
    }
    key = helper.camelCase(key)
    if (isStringable === false) {
      // store non-serializable value in special element property
      helper.elInitMicroDomInfo(el)
      el[helper.rand].data[key] = value
      return
    }
    el.dataset[key] = stringified
  }

  const getValue = function (el, name) {
    name = helper.camelCase(name)
    if (typeof el === 'undefined') {
      return undefined
    }
    const value = el[helper.rand]?.data?.[name]
      ? el[helper.rand].data[name]
      : el.dataset[name]
    return safeJsonParse(value)
  }

  const removeDataHelper = function (el, name) {
    name = helper.camelCase(name)
    if (el[helper.rand]) {
      delete el[helper.rand].data[name]
    }
    delete el.dataset[name]
  }

  const safeJsonParse = function (value) {
    try {
      value = JSON.parse(value)
    } catch (e) {
      // do nothing
    }
    return value
  }

  function data (name, value) {
    if (typeof name === 'undefined') {
      // return all data
      if (typeof this[0] === 'undefined') {
        return {}
      }
      const data = {}
      const nonSerializable = this[0][helper.rand]?.data || {}
      for (const key in this[0].dataset) {
        data[key] = safeJsonParse(this[0].dataset[key])
      }
      return helper.extend(data, nonSerializable)
    }
    if (typeof name !== 'object' && typeof value !== 'undefined') {
      // we're setting a single value -> convert to object
      name = {[name]: value}
    }
    if (typeof name === 'object') {
      // setting value(s)
      return this.each((el) => {
        for (let [key, value] of Object.entries(name)) {
          setValue(el, key, value)
        }
      })
    }
    return getValue(this[0], name)
  }

  function removeData (mixed) {
    mixed = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed
    return this.each((el) => {
      for (let name of mixed) {
        removeDataHelper(el, name)
      }
    })
  }

  Object.assign(MicroDom.prototype, {
    data,
    removeData,
  })

}
