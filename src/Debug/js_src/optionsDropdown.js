import $ from 'zest'
import { cookieGet, cookieRemove, cookieSet } from './http.js'

var $root
var config
var KEYCODE_ESC = 27
var menu = '<div class="debug-options" aria-labelledby="debug-options-toggle">' +
  '<div class="debug-options-body">' +
    '<label>{string:cfg.theme} <select name="theme">' +
      '<option value="auto">{string:cfg.theme.auto}</option>' +
      '<option value="light">{string:cfg.theme.light}</option>' +
      '<option value="dark">{string:cfg.theme.dark}</option>' +
    '</select></label>' +
    '<label><input type="checkbox" name="debugCookie" /> {string:cfg.cookie}</label>' +
    '<label><input type="checkbox" name="persistDrawer" /> {string:cfg.persist-drawer}</label>' +
    '<label><input type="checkbox" name="linkFiles" /> {string:cfg.link-files}</label>' +
    '<div class="form-group">' +
      '<label for="linkFilesTemplate">{string:cfg.link-template}</label>' +
      '<input id="linkFilesTemplate" name="linkFilesTemplate" />' +
    '</div>' +
    '<hr class="dropdown-divider" />' +
    '<a href="http://www.bradkent.com/php/debug" target="_blank">{string:cfg.documentation}</a>' +
  '</div>' +
  '</div>'

export function init ($debugRoot) {
  $root = $debugRoot
  config = $root.data('config')

  addDropdown()

  $root.find('.debug-options-toggle')
    .on('click', onChangeDebugOptionsToggle)

  $root.find('select[name=theme]')
    .on('change', onChangeTheme)
    .val(config.get('theme'))

  $root.find('input[name=debugCookie]')
    .on('change', onChangeDebugCookie)
    .prop('checked', config.get('debugKey') && cookieGet('debug') === config.get('debugKey'))
    .prop('disabled', !config.get('debugKey'))
    .closest('label').toggleClass('disabled', !config.get('debugKey'))

  $root.find('input[name=persistDrawer]')
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
  $menuBar.find('.float-right').prepend('<button class="debug-options-toggle" type="button" data-toggle="debug-options" aria-label="' + config.dict.get('word.options') + '" aria-haspopup="true" aria-expanded="false">' +
      '<i class="fa fa-ellipsis-v fa-fw"></i>' +
    '</button>'
  )
  menu = config.dict.replaceTokens(menu)
  $menuBar.append(menu)
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
