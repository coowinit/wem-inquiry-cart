# WEM Inquiry Cart

轻量级 WordPress **B2B 产品询盘购物车**插件：访客选择产品、调整数量，再通过网站**原有联系表单**提交询盘。适用于木塑地板、墙板等不需要在线支付的产品展示型网站。

> **当前正式版本：v1.0.0（基于已验证的 v0.1.4 功能）。** 已完成实际站点的添加、更新、删除、悬浮购物车、提示信息、表单对接及询盘邮件测试。不同主题、多个访客和缓存环境仍应按目标站点单独验证。

## 功能概览

- 产品卡片和详情页的 Add to Cart 按钮、成功状态反馈。
- 购物车列表含图片/标题链接、数量 Update、Delete 及成功/错误提示。
- 右侧悬浮购物车及实时数量，自动显示与手动短代码二选一。
- 使用独立 `.wic-cart-ui` 样式作用域与 SVG 图标，不依赖 WooCommerce、Bootstrap、jQuery 或 Font Awesome。
- 匿名访客可使用购物车；随机 HttpOnly Cookie + WordPress Transient 保存产品 ID 和数量。
- 后台中文设置页，配置产品文章类型、图片元字段、购物车页面与悬浮按钮。
- 提供两个 PHP 接口，让**网站原有表单**读取产品清单，并在成功提交后清空购物车。

## 效果演示

[HTML27：购物车前端交互预览](https://coowinit.github.io/responsive-html-templates/html27/index.html) · [HTML27 源码](https://github.com/coowinit/responsive-html-templates/tree/main/html27)

HTML27 使用浏览器演示数据，**不是**真实 WordPress 后端，不发送邮件。

另外提供 [通用表单集成测试示例](examples/form-integration/README.md)，可在独立 WordPress 测试站安装。默认只做模拟校验，只有明确开启时才发送测试邮件。

## 安装

1. 下载单独的可安装包 `wem-inquiry-cart-v1.0.0.zip`（**不要直接上传整个 GitHub 项目压缩包**）。
2. WordPress 后台 → 插件 → 安装插件 → 上传插件，安装并启用。
3. 在 **设置 → Inquiry Cart**（或插件列表的 **设置**）中选择产品文章类型，例如 `products`、图片字段例如 `product_pic01`。
4. 创建一个购物车页面，加入 `[cart]`，然后在插件设置中选中该页面。
5. 选择在后台自动显示悬浮购物车，或在合适位置调用 `[mini_cart]`，不要同时启用两种方式。
6. 在产品列表或详情页面放置 `[add_to_cart]`，按下方测试清单测试。

插件**不自动创建购物车页面或联系表单**。

## 短代码

| 短代码 | 用途 |
|---|---|
| `[add_to_cart]` | 当前产品 WordPress Loop 中添加按钮 |
| `[add_to_cart product_id="123"]` | 指定产品 ID 的添加按钮；可传 `quantity="1"` |
| `[cart]` | 购物车产品清单、更新/删除和操作提示 |
| `[mini_cart]` | 浮动购物车及数量角标 |
| `[cart_summary]` | 供页面展示的简易清单，**不能代替服务器端接口** |

主题调用示例：

```php
if ( shortcode_exists( 'add_to_cart' ) ) {
    echo do_shortcode( '[add_to_cart product_id="' . absint( get_the_ID() ) . '"]' );
}
```

### 样式与资源

- `assets/css/cart.css`：插件前端专用 CSS，所有组件选择器限制在 `.wic-cart-ui` 范围内。
- `assets/icons/`：三个独立 SVG，分别对应加入、已加入、悬浮按钮，可独立替换。
- HTML27 仅供设计与复用参考；**不要**把其 `cart-demo.js` 当作真实服务器购物车。

## 与已有表单集成

插件不控制姓名、电话、国家、邮箱等联系字段，也不发送正式询盘邮件。

```php
$items = function_exists( 'wem_ic_get_cart_items' )
    ? wem_ic_get_cart_items()
    : array();

// 在网站原有 PHP 表单提交程序中验证字段、邮件路由并发送。
// $items 每项含 product_id、name、quantity、url、image。

if ( $sent && function_exists( 'wem_ic_clear_cart' ) ) {
    wem_ic_clear_cart(); // 仅在原有表单成功处理后清空
}
```

注意：`$sent` 由原有网站的邮件代码提供；此处仅演示接入位置，不能单独运行发送邮件。

- [表单接入原理、PHP 示例及适配边界](docs/form-integration.md)
- [通用表单测试示例及安装方法](examples/form-integration/README.md)

## 常见设置与已知限制

| 项目 | 说明 |
|---|---|
| 产品类型 | 仅允许加入后台指定类型且已发布的文章 |
| 图片字段 | 默认 `product_pic01`，缺失时回退到特色图片 |
| 数量上限 | 单种商品 1–99，最多 50 种商品 |
| 购物车有效期 | 默认最长 7 天；Transient 可能提前被清理，不保证完整保留 |
| 缓存 | 真实网站需检查 Cookie、页面缓存、`admin-ajax.php`，避免不同访客的数据混淆 |
| 停用插件 | 主题/页面需要针对未注册的短代码作兼容处理；插件停用后无法执行自身代码 |
| 邮件 | 不内置 SMTP、地区路由、联系表单或客户数据管理 |
| 兼容性 | 第三方表单需适配服务器端 Hook；不宣称直接兼容所有表单插件 |

## 文件组织

```text
wem-inquiry-cart/
├── wem-inquiry-cart.php
├── includes/
│   ├── class-cart-store.php
│   └── class-plugin.php
├── assets/
│   ├── css/cart.css
│   ├── js/cart.js
│   └── icons/（三个独立 SVG）
├── uninstall.php
├── examples/
│   └── form-integration/（独立示例及说明）
├── docs/
│   ├── form-integration.md
│   └── testing.md
└── README.md
```

`examples/`、`docs/` 只存在于 GitHub 项目完整包；**正式插件安装 ZIP 不包含示例和开发文档**。

## 测试与发布

[发布前测试清单](docs/testing.md) · [可选表单示例](examples/form-integration/README.md)

正式版 v1.0.0 延续已验证的 v0.1.4 核心业务代码，仅统一版本标识和发布文档，不新增购物车业务功能。表单示例另设可选插件，仅适合测试站。运行测试前请检查旧主题是否有同名短代码与 AJAX Hook，避免双重注册。

## 版本记录

- **v1.0.0**：首个正式版本；整理完整源码、文档和可选表单测试示例，核心功能沿用 v0.1.4。
- **v0.1.4**：购物车数量更新与删除操作提示；当前测试基线。
- **v0.1.3**：插件列表的「设置」快捷入口。
- **v0.1.2**：原网站视觉和表单迁移适配修正。
- **v0.1.1**：后台设置中文说明。
- **v0.1.0**：购物车核心短代码与服务端接口。

## 安全和隐私

请勿上传生产网站的 `functions.php`、SMTP 授权码、真实客户邮件、收件人分配表或数据库到公开仓库。站点现有的联系表单依然需要 Nonce、字段校验、限流和隐私政策；示例插件**不可作为可公开部署的生产级邮件表单**。
