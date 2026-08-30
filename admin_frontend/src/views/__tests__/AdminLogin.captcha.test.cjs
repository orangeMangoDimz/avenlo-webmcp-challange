const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

test('admin login does not contain browser-side CAPTCHA', () => {
  const componentPath = path.join(__dirname, '../AdminLogin.vue')
  const source = fs.readFileSync(componentPath, 'utf8')

  assert.doesNotMatch(source, /captcha/i)
})
