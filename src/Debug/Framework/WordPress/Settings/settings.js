(function ($) {
  const toggles = {
    '#debugConsoleForPhp_enableEmailer': ['#debugConsoleForPhp_emailTo'],
    '#debugConsoleForPhp_plugins_routeDiscord_enabled': ['#debugConsoleForPhp_plugins_routeDiscord_webhookUrl'],
    '#debugConsoleForPhp_plugins_routeSlack_enabled': [
      '#debugConsoleForPhp_plugins_routeSlack_enabled-description',
      '#debugConsoleForPhp_plugins_routeSlack_webhookUrl',
      '#debugConsoleForPhp_plugins_routeSlack_token',
      '#debugConsoleForPhp_plugins_routeSlack_channel',
    ],
    '#debugConsoleForPhp_plugins_routeTeams_enabled': ['#debugConsoleForPhp_plugins_routeTeams_webhookUrl'],
  }
  $(function () {
    $('.debug').on('resize.debug', function () {
      const height = $(this).hasClass('debug-drawer-open')
        ? ($(this).height() - 60) + 'px'
        : '';
      $('#wpbody').style('marginBottom', height)
      // there is some javascript sticky stuff that wordpress is doing for #adminmenuwrap
      // $('#adminmenu')css('marginBottom', ($root.height() + 8) + 'px')
    })
    $.each(toggles, function (dest, checkbox) {
      $(checkbox).on('change', function () {
        const isChecked = $(this).is(':checked');
        $(dest.join(', ')).each(function () {
          const $this = $(this);
          $this.is(':input')
            ? $this.closest('tr').toggle(isChecked)
            : $this.toggle(isChecked);
        })
      }).trigger('change');
    });
  });
}(window.zest));
