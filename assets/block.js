(function (blocks, element, i18n, blockEditor, components) {
  const el = element.createElement;
  const __ = i18n.__;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const SelectControl = components.SelectControl;

  blocks.registerBlockType('urlaub-post/notice', {
    title: __('Urlaub Notice', 'urlaub_post'),
    icon: 'calendar-alt',
    category: 'widgets',
    attributes: {
      view: {
        type: 'string',
        default: 'full',
      },
    },
    edit: function (props) {
      return el(
        element.Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Anzeige', 'urlaub_post'), initialOpen: true },
            el(SelectControl, {
              label: __('Ansicht', 'urlaub_post'),
              value: props.attributes.view,
              options: [
                { label: __('Voll', 'urlaub_post'), value: 'full' },
                { label: __('Kompakt', 'urlaub_post'), value: 'compact' },
              ],
              onChange: function (value) {
                props.setAttributes({ view: value });
              },
            })
          )
        ),
        el('p', {}, __('Urlaub Notice (dynamischer Block). Vorschau im Frontend.', 'urlaub_post'))
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components);
