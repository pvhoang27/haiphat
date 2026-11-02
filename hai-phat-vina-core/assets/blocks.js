(function (wp) {
  'use strict';
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
  var specs = {
    'company-info': { title: 'Thông tin công ty', icon: 'building', description: 'Thông tin dùng chung. Sửa một lần ở Thông tin công ty để cập nhật mọi nơi.', attributes: { layout: { type: 'string', default: 'contact', enum: ['contact', 'footer', 'branches', 'bank', 'phone'] } } },
    'quote-form': { title: 'Form yêu cầu báo giá', icon: 'email', description: 'Nhận yêu cầu và lưu riêng tư trong mục Yêu cầu báo giá.', attributes: { variant: { type: 'string', default: 'compact' }, needs: { type: 'array', items: { type: 'string' } } } },
    'product-price': { title: 'Giá sản phẩm', icon: 'tag', description: 'Lấy giá từ Thông tin sản phẩm.', usesContext: ['postId', 'postType'] },
    'product-specs': { title: 'Quy cách sản phẩm', icon: 'list-view', description: 'Lấy quy cách, ứng dụng và bảo hành từ Thông tin sản phẩm.', usesContext: ['postId', 'postType'] },
    'floating-contact': { title: 'Nút gọi điện và Zalo', icon: 'phone', description: 'Liên kết hotline và Zalo lấy từ Thông tin công ty.' },
    'article-toc': { title: 'Mục lục bài viết', icon: 'list-view', description: 'Tự tạo liên kết đến các tiêu đề H2/H3 khi bài viết có ít nhất hai mục.' }
  };
  var copyFields = {
    'company-info': { phoneCaption: ['Chú thích hotline', 'Tư vấn & báo giá'], companyLabel: ['Nhãn doanh nghiệp', 'Doanh nghiệp'], taxLabel: ['Nhãn mã số thuế', 'Mã số thuế'], hotlineLabel: ['Nhãn điện thoại', 'Hotline'], emailLabel: ['Nhãn email', 'Email'], branchPrefix: ['Tên nhóm cơ sở', 'Cơ sở'], directionsLabel: ['Chữ liên kết bản đồ', 'Xem đường đi'], bankLabel: ['Nhãn ngân hàng', 'Ngân hàng'], ownerLabel: ['Nhãn chủ tài khoản', 'Chủ tài khoản'], accountLabel: ['Nhãn số tài khoản', 'Số tài khoản'], paymentNote: ['Ghi chú thanh toán', 'Vui lòng xác nhận thông tin thanh toán qua hotline trước khi chuyển khoản.'], zaloLabel: ['Chữ liên kết Zalo', 'Trao đổi qua Zalo'] },
    'quote-form': { nameLabel: ['Nhãn họ tên', 'Họ và tên'], phoneLabel: ['Nhãn điện thoại', 'Số điện thoại'], needLabel: ['Nhãn sản phẩm quan tâm', 'Bạn đang quan tâm'], locationLabel: ['Nhãn khu vực', 'Khu vực cần tư vấn'], messageLabel: ['Nhãn nội dung', 'Nhu cầu của bạn'], consentText: ['Nội dung đồng ý liên hệ', 'Tôi đồng ý để Hải Phát Vina sử dụng thông tin trên để liên hệ tư vấn.'], privacyLabel: ['Chữ liên kết chính sách', 'Chính sách bảo mật'], submitLabel: ['Chữ trên nút gửi', 'Gửi yêu cầu báo giá'], namePlaceholder: ['Gợi ý nhập tên', 'Tên của bạn'], phonePlaceholder: ['Gợi ý nhập điện thoại', 'Số điện thoại để tư vấn'], needPlaceholder: ['Gợi ý chọn sản phẩm', 'Chọn sản phẩm / dịch vụ'], locationPlaceholder: ['Gợi ý nhập khu vực', 'Quận / huyện, tỉnh / thành'], messagePlaceholder: ['Gợi ý nhập nhu cầu', 'Ví dụ: mái xếp cho sân rộng 5 × 8 m, tại Hà Đông…'] },
    'product-price': { fallbackLabel: ['Chữ khi chưa có giá', 'Liên hệ báo giá'] },
    'product-specs': { materialLabel: ['Nhãn quy cách', 'Chất liệu / quy cách'], applicationLabel: ['Nhãn ứng dụng', 'Ứng dụng'], warrantyLabel: ['Nhãn bảo hành', 'Bảo hành'] },
    'floating-contact': { zaloLabel: ['Chữ nút Zalo', 'Chat Zalo'], phoneLabel: ['Chữ nút gọi ({phone} là số hotline)', 'Gọi {phone}'] },
    'article-toc': { titleLabel: ['Tiêu đề mục lục', 'Trong bài viết này'] }
  };
  Object.keys(specs).forEach(function (slug) {
    var spec = specs[slug];
    wp.blocks.registerBlockType('hpv/' + slug, {
      apiVersion: 3, title: spec.title, icon: spec.icon, description: spec.description,
      category: 'hai-phat-vina-business', attributes: Object.assign({}, spec.attributes || {}, { labels: { type: 'object', default: {} } }), usesContext: spec.usesContext || [],
      supports: { html: false, align: ['wide', 'full'], spacing: { margin: true, padding: true }, color: { text: true, background: true, link: true }, typography: { fontSize: true, lineHeight: true, __experimentalFontFamily: true } },
      edit: function (props) {
        var controls = [];
        if (slug === 'company-info') {
          controls.push(el(wp.components.SelectControl, { key: 'layout', label: 'Nội dung hiển thị', value: props.attributes.layout, options: [
            { label: 'Thông tin liên hệ đầy đủ', value: 'contact' }, { label: 'Thông tin ở chân trang', value: 'footer' },
            { label: 'Danh sách cơ sở', value: 'branches' }, { label: 'Thông tin chuyển khoản', value: 'bank' }, { label: 'Hotline', value: 'phone' }
          ], onChange: function (layout) { props.setAttributes({ layout: layout }); } }));
        }
        controls.push(el('p', { key: 'description' }, spec.description));
        Object.keys(copyFields[slug] || {}).forEach(function (key) {
          var field = copyFields[slug][key];
          controls.push(el(wp.components.TextControl, { key: key, label: field[0], placeholder: field[1], value: (props.attributes.labels || {})[key] || '', onChange: function (value) {
            var labels = Object.assign({}, props.attributes.labels || {}); labels[key] = value; props.setAttributes({ labels: labels });
          } }));
        });
        if (slug === 'quote-form') {
          controls.push(el(wp.components.TextareaControl, { key: 'needs', label: 'Các lựa chọn sản phẩm (mỗi dòng một lựa chọn)', help: 'Để trống để dùng danh sách mặc định. Tối đa 30 lựa chọn.', value: (props.attributes.needs || []).join('\n'), onChange: function (value) {
            props.setAttributes({ needs: value.trim() ? value.split('\n').map(function (line) { return line.trim(); }).filter(Boolean).slice(0, 30) : undefined });
          } }));
        }
        if (window.hpvBlockEditor && ['company-info', 'floating-contact'].indexOf(slug) !== -1) {
          controls.push(el(wp.components.Button, { key: 'settings', variant: 'secondary', href: window.hpvBlockEditor.settingsUrl, target: '_blank', rel: 'noopener noreferrer' }, 'Sửa thông tin công ty'));
        }
        var preview;
        if (spec.usesContext && !props.context.postId) {
          preview = el(wp.components.Placeholder, { icon: spec.icon, label: spec.title }, 'Nội dung tự hiển thị theo sản phẩm khi xem website. Sửa thông tin trong mục Sản phẩm.');
        } else if (slug === 'floating-contact' || slug === 'article-toc') {
          preview = el(wp.components.Placeholder, { icon: spec.icon, label: spec.title }, spec.description);
        } else {
          preview = el('div', { className: 'hpv-block-preview', inert: '' }, el(ServerSideRender, { block: 'hpv/' + slug, attributes: props.attributes, urlQueryArgs: props.context.postId ? { post_id: props.context.postId } : {} }));
        }
        return el(Fragment, null,
          el(wp.blockEditor.InspectorControls, null, el(wp.components.PanelBody, { title: spec.title }, controls)),
          el('div', wp.blockEditor.useBlockProps(), preview)
        );
      },
      save: function () { return null; }
    });
  });
})(window.wp);
