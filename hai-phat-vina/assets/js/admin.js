/* global wp, jQuery */
jQuery(($) => {
  const frames = {};
  $('.hpv-choose-image').on('click', function () {
    const key = this.dataset.imageKey;
    if (frames[key]) { frames[key].open(); return; }
    const frame = wp.media({title: 'Chọn ảnh cho website', button: {text: 'Sử dụng ảnh này'}, multiple: false, library: {type: 'image'}});
    frames[key] = frame;
    frame.on('select', () => {
      const image = frame.state().get('selection').first().toJSON();
      $(`#hpv-image-${key}`).val(image.id);
      const src = image.sizes?.medium?.url || image.url;
      $(`#hpv-preview-${key}`).empty().append($('<img>', {src, alt: image.alt || 'Ảnh website'}));
    });
    frame.open();
  });
  $('.hpv-clear-image').on('click', function () { const key = this.dataset.imageKey; $(`#hpv-image-${key}`).val('0'); $(`#hpv-preview-${key}`).empty(); });
});
