(function ($, wp) {
  'use strict';

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function parseDateYmd(ymd) {
    if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
      return null;
    }
    var parts = ymd.split('-').map(function (n) { return parseInt(n, 10); });
    return new Date(parts[0], parts[1] - 1, parts[2], 0, 0, 0, 0);
  }

  function buildPublishDate(vonYmd) {
    var base = parseDateYmd(vonYmd);
    if (!base) {
      return null;
    }

    var preDays = parseInt((window.IGWUrlaubPostSchedule && window.IGWUrlaubPostSchedule.preDays) || 0, 10);
    if (isNaN(preDays) || preDays < 0) {
      preDays = 0;
    }

    var time = (window.IGWUrlaubPostSchedule && window.IGWUrlaubPostSchedule.time) || '04:30:00';
    var timeParts = time.split(':');
    var hh = parseInt(timeParts[0] || '4', 10);
    var mm = parseInt(timeParts[1] || '30', 10);
    var ss = parseInt(timeParts[2] || '0', 10);

    base.setDate(base.getDate() - preDays);
    base.setHours(hh, mm, ss, 0);

    return base;
  }

  function formatLocalIso(dt) {
    return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
  }

  function setGutenbergDate(dt) {
    if (!wp || !wp.data || !wp.data.dispatch) {
      return false;
    }
    try {
      wp.data.dispatch('core/editor').editPost({ date: formatLocalIso(dt) });
      return true;
    } catch (e) {
      return false;
    }
  }

  function setClassicDate(dt) {
    var $editTs = $('a.edit-timestamp');
    if ($editTs.length) {
      $editTs.trigger('click');
    }

    var $aa = $('#aa');
    var $mm = $('#mm');
    var $jj = $('#jj');
    var $hh = $('#hh');
    var $mn = $('#mn');

    if (!($aa.length && $mm.length && $jj.length && $hh.length && $mn.length)) {
      return false;
    }

    $aa.val(dt.getFullYear());
    $mm.val(pad(dt.getMonth() + 1));
    $jj.val(pad(dt.getDate()));
    $hh.val(pad(dt.getHours()));
    $mn.val(pad(dt.getMinutes()));

    var $saveTs = $('a.save-timestamp');
    if ($saveTs.length) {
      $saveTs.trigger('click');
    }

    return true;
  }

  function updatePreview(dt) {
    var $preview = $('#igw_wp_urlaub_post_schedule_preview');
    if (!$preview.length) {
      return;
    }

    if (!dt) {
      $preview.text('');
      return;
    }

    var prefix = (window.IGWUrlaubPostSchedule && window.IGWUrlaubPostSchedule.i18n && window.IGWUrlaubPostSchedule.i18n.previewPrefix) || 'Geplante Veröffentlichung:';
    var formatted = pad(dt.getDate()) + '.' + pad(dt.getMonth() + 1) + '.' + dt.getFullYear() + ' ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    $preview.text(prefix + ' ' + formatted);
  }

  function applySchedule() {
    var von = $('#igw_wp_urlaub_post_von').val();
    var dt = buildPublishDate(von);

    updatePreview(dt);
    if (!dt) {
      return;
    }

    if (!setGutenbergDate(dt)) {
      setClassicDate(dt);
    }
  }



  function hideAddNewInputs() {
    var isNew = !!(window.IGWUrlaubPostSchedule && window.IGWUrlaubPostSchedule.isNewPost);
    if (!isNew) {
      return;
    }

    $('#titlediv, #categorydiv, #tagsdiv-post_tag').hide();

    if (window.wp && window.wp.data && window.wp.data.dispatch) {
      try {
        window.wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-category');
        window.wp.data.dispatch('core/edit-post').removeEditorPanel('taxonomy-panel-post_tag');
      } catch (e) {
        // no-op
      }
    }

    var css = document.createElement('style');
    css.id = 'igw-urlaub-post-hide-add-new-ui';
    css.textContent = [
      '.post-type-urlaub_post.post-new-php #titlediv { display:none !important; }',
      '.post-type-urlaub_post.post-new-php #categorydiv { display:none !important; }',
      '.post-type-urlaub_post.post-new-php #tagsdiv-post_tag { display:none !important; }',
      '.post-type-urlaub_post .editor-post-title { display:none !important; }'
    ].join('\n');

    if (!document.getElementById(css.id)) {
      document.head.appendChild(css);
    }
  }

  $(function () {
    var $von = $('#igw_wp_urlaub_post_von');
    var $bis = $('#igw_wp_urlaub_post_bis');
    var $btn = $('#igw_wp_urlaub_post_apply_schedule');

    hideAddNewInputs();

    if (!$von.length) {
      return;
    }

    $von.on('change input', applySchedule);
    $bis.on('change input', applySchedule);

    $btn.on('click', function (event) {
      event.preventDefault();
      applySchedule();
    });

    applySchedule();
  });
})(jQuery, window.wp);
