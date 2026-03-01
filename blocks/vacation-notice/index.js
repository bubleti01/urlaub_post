(function (blocks, blockEditor, element, components, i18n, serverSideRender) {
  const el = element.createElement;
  const InspectorControls = blockEditor.InspectorControls;
  const PanelBody = components.PanelBody;
  const ToggleControl = components.ToggleControl;
  const TextControl = components.TextControl;

  blocks.registerBlockType('urlaub-post/vacation-notice', {
    edit: function (props) {
      const attributes = props.attributes;

      return el(
        element.Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: i18n.__('Anzeige', 'urlaub_post'), initialOpen: true },
            el(ToggleControl, {
              label: i18n.__('Bild anzeigen', 'urlaub_post'),
              checked: !!attributes.showImage,
              onChange: function (value) {
                props.setAttributes({ showImage: value });
              }
            }),
            el(ToggleControl, {
              label: i18n.__('Datum anzeigen', 'urlaub_post'),
              checked: !!attributes.showDates,
              onChange: function (value) {
                props.setAttributes({ showDates: value });
              }
            }),
            el(TextControl, {
              label: i18n.__('Limit (0 = unbegrenzt)', 'urlaub_post'),
              type: 'number',
              value: attributes.limit,
              onChange: function (value) {
                props.setAttributes({ limit: parseInt(value || 0, 10) || 0 });
              }
            }),
            el(TextControl, {
              label: i18n.__('Spezifische ID (optional)', 'urlaub_post'),
              type: 'number',
              value: attributes.id,
              onChange: function (value) {
                props.setAttributes({ id: parseInt(value || 0, 10) || 0 });
              }
            })
          )
        ),
        el(serverSideRender, {
          block: 'urlaub-post/vacation-notice',
          attributes: attributes
        })
      );
    },
    save: function () {
      return null;
    }
  });
})(
  window.wp.blocks,
  window.wp.blockEditor,
  window.wp.element,
  window.wp.components,
  window.wp.i18n,
  window.wp.serverSideRender
);
