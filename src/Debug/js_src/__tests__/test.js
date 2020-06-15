const fs = require('fs')
const path = require('path')

describe('my first test', () => {

  var html

  beforeAll(() => {
    html = fs.readFileSync(path.resolve(__dirname, './test.html')).toString()
    document.body.innerHTML = html
  })

  test('test passes', () => {
    expect(true).toBeTruthy()
  })
})
