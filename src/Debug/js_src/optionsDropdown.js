import $ from 'jquery'
import { cookieGet, cookieRemove, cookieSet } from './http.js'

var $root
var config
var KEYCODE_ESC = 27

export function init ($debugRoot) {
  $root = $debugRoot
  config = $root.data('config')

  addDropdown()

  $root.find('.debug-options-toggle')
    .on('click', onChangeDebugOptionsToggle)

  $('select[name=theme]')
    .on('change', onChangeTheme)
    .val(config.get('theme'))

  $('input[name=debugCookie]')
    .on('change', onChangeDebugCookie)
    .prop('checked', config.get('debugKey') && cookieGet('debug') === config.get('debugKey'))
  if (!config.get('debugKey')) {
    $('input[name=debugCookie]')
      .prop('disabled', true)
      .closest('label').addClass('disabled')
  }

  $('input[name=persistDrawer]')
    .on('change', onChangePersistDrawer)
    .prop('checked', config.get('persistDrawer'))

  $root.find('input[name=linkFiles]')
    .on('change', onChangeLinkFiles)
    .prop('checked', config.get('linkFiles'))
    .trigger('change')

  $root.find('input[name=linkFilesTemplate]')
    .on('change', onChangeLinkFilesTemplate)
    .val(config.get('linkFilesTemplate'))
}

function addDropdown () {
  var $menuBar = $root.find('.debug-menu-bar')
  $menuBar.find('.float-right').prepend('<button class="debug-options-toggle" type="button" data-toggle="debug-options" aria-label="Options" aria-haspopup="true" aria-expanded="false">' +
      '<i class="fa fa-ellipsis-v fa-fw"></i>' +
    '</button>'
  )
  $menuBar.append('<div class="debug-options" aria-labelledby="debug-options-toggle">' +
      '<div class="debug-options-body">' +
        '<label>Theme <select name="theme">' +
          '<option value="auto">Auto</option>' +
          '<option value="light">Light</option>' +
          '<option value="dark">Dark</option>' +
        '</select></label>' +
        '<label><input type="checkbox" name="debugCookie" /> Debug Cookie</label>' +
        '<label><input type="checkbox" name="persistDrawer" /> Keep Open/Closed</label>' +
        '<label><input type="checkbox" name="linkFiles" /> Create file links</label>' +
        '<div class="form-group">' +
          '<label for="linkFilesTemplate">Link Template</label>' +
          '<input id="linkFilesTemplate" name="linkFilesTemplate" />' +
        '</div>' +
        '<hr class="dropdown-divider" />' +
        '<a href="http://www.bradkent.com/php/debug" target="_blank">Documentation</a>' +
      '</div>' +
    '</div>'
  )
  if (!config.get('drawer')) {
    $menuBar.find('input[name=persistDrawer]').closest('label').remove()
  }
}

function onBodyClick (e) {
  if ($root.find('.debug-options').find(e.target).length === 0) {
    // we clicked outside the dropdown
    close()
  }
}

function onBodyKeyup (e) {
  if (e.keyCode === KEYCODE_ESC) {
    close()
  }
}

function onChangeDebugCookie () {
  var isChecked = $(this).is(':checked')
  isChecked
    ? cookieSet('debug', config.get('debugKey'), 7)
    : cookieRemove('debug')
}

function onChangeDebugOptionsToggle (e) {
  var isVis = $(this).closest('.debug-bar').find('.debug-options').is('.show')
  $root = $(this).closest('.debug')
  isVis
    ? close()
    : open()
  e.stopPropagation()
}

function onChangeLinkFiles () {
  var isChecked = $(this).prop('checked')
  var $formGroup = $(this).closest('.debug-options').find('input[name=linkFilesTemplate]').closest('.form-group')
  isChecked
    ? $formGroup.slideDown()
    : $formGroup.slideUp()
  config.set('linkFiles', isChecked)
  $('input[name=linkFilesTemplate]').trigger('change')
}

function onChangeLinkFilesTemplate () {
  var val = $(this).val()
  config.set('linkFilesTemplate', val)
  $root.trigger('config.debug.updated', 'linkFilesTemplate')
}

function onChangePersistDrawer () {
  var isChecked = $(this).is(':checked')
  config.set({
    persistDrawer: isChecked,
    openDrawer: isChecked,
    openSidebar: true
  })
}

function onChangeTheme () {
  config.set('theme', $(this).val())
  $root.attr('data-theme', config.themeGet())
}

function open () {
  $root.find('.debug-options').addClass('show')
  $('body').on('click', onBodyClick)
  $('body').on('keyup', onBodyKeyup)
}

function close () {
  $root.find('.debug-options').removeClass('show')
  $('body').off('click', onBodyClick)
  $('body').off('keyup', onBodyKeyup)
}
