(function (blocks, element, i18n) {
  const el = element.createElement;
  const __ = i18n.__;

  blocks.registerBlockType('igw-wp-urlaub-post/urlaub', {
    title: __('IGW Urlaub', 'igw_wp_urlaub_post'),
    icon: 'calendar-alt',
    category: 'widgets',
    edit: function () {
      return el('p', {}, __('Dieser Block rendert die Urlaub-Übersicht im Frontend.', 'igw_wp_urlaub_post'));
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n);
