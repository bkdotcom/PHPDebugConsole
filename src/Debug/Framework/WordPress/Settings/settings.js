(function ($) {
  var toggles = {
    '#phpdebugconsole_enableEmailer': ['#phpdebugconsole_emailTo'],
    '#phpdebugconsole_plugins_routeDiscord_enabled': ['#phpdebugconsole_plugins_routeDiscord_webhookUrl'],
    '#phpdebugconsole_plugins_routeSlack_enabled': [
      '#phpdebugconsole_plugins_routeSlack_enabled-description',
      '#phpdebugconsole_plugins_routeSlack_webhookUrl',
      '#phpdebugconsole_plugins_routeSlack_token',
      '#phpdebugconsole_plugins_routeSlack_channel',
    ],
    '#phpdebugconsole_plugins_routeTeams_enabled': ['#phpdebugconsole_plugins_routeTeams_webhookUrl'],
  }
  $(function () {
    $('.debug').on('resize.debug', function () {
      var height = $(this).hasClass('debug-drawer-open')
        ? ($(this).height() - 60) + 'px'
        : '';
      $('#wpbody').style('marginBottom', height)
      // there is some javascript sticky stuff that wordpress is doing for #adminmenuwrap
      // $('#adminmenu')css('marginBottom', ($root.height() + 8) + 'px')
    })
    $.each(toggles, function (dest, checkbox) {
      $(checkbox).on('change', function () {
        var isChecked = $(this).is(':checked');
        $(dest.join(', ')).each(function () {
          var $this = $(this);
          $this.is(':input')
            ? $this.closest('tr').toggle(isChecked)
            : $this.toggle(isChecked);
        })
      }).trigger('change');
    });
  });
}(window.zest));
