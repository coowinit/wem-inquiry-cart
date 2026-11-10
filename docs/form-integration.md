# 表单集成指南

## 架构边界

WEM Inquiry Cart 只存储当前访客的产品 ID、数量；联系表单的字段、验证、收件人、邮件格式和发送方式由网站负责。HTML27 仅为静态交互演示。

## WordPress 服务端集成

**请在现有表单的服务器端 POST/AJAX 处理函数中调用接口，而不是在浏览器提交隐藏的产品名称。**

```php
if ( ! function_exists( 'wem_ic_get_cart_items' ) ) {
    // 购物车插件未启用：拒绝询盘或使用网站原有流程。
    return;
}

$items = wem_ic_get_cart_items();
if ( empty( $items ) ) {
    // 没有商品：返回前端错误提示。
    return;
}

// 下方仅是邮件商品列表示例；表单校验、Nonce、限流及收件人路由需由网站实现。
$lines = array();
foreach ( $items as $item ) {
    $lines[] = sprintf(
        '%s × %d — %s',
        sanitize_text_field( $item['name'] ),
        absint( $item['quantity'] ),
        esc_url_raw( $item['url'] )
    );
}
$cart_text = implode( "\n", $lines );

// 将 $cart_text 添加到网站现有的邮件正文；仅在原有 wp_mail() 返回 true 后：
if ( $sent && function_exists( 'wem_ic_clear_cart' ) ) {
    wem_ic_clear_cart();
}
```

注意 `$sent` 为**原有表单代码的发送结果**，不是本段示例自动生成的变量。

- `wem_ic_get_cart_items()` 返回商品数组，字段：`product_id`、`name`、`quantity`、`url`、`image`。
- `wem_ic_clear_cart()` 只清空当前访客购物车，**不可在表单展示或验证失败时调用**。
- 邮件内容中的商品数据须根据所用格式进行恰当转义；不要信任用户可修改的隐藏商品字段。
- `wp_mail()` 返回 true 不等于送达；验收时仍需检查实际收件。

## 可独立安装的通用测试表单

见 [examples/form-integration/README.md](../examples/form-integration/README.md)。

该示例默认**模拟提交**，不发邮件、不清空购物车；只有测试站明确开启常量时才尝试邮件发送。

## 不同网站的集成方式

- 自写 PHP 表单：在已有服务端处理函数中调用两个接口，保持原表单字段和提交逻辑。
- AJAX 表单：仅在合法请求的服务器处理程序中读取购物车，成功发送后才清空；前端接收明确的成功/失败响应。
- 第三方表单插件：需针对对应表单插件的提交成功 Hook 单独开发适配；本项目不宣称开箱兼容所有表单。
- 外部 SaaS 表单：通常无法直接访问 WordPress 服务器端访客购物车，需要额外安全 API。

## 缓存与停用

- 确保访客 Cookie、`admin-post.php`、`admin-ajax.php` 不被 CDN 误缓存。
- 购物车和示例测试页需要动态返回；排除页面缓存。
- 主题直接调用短代码时，使用 `shortcode_exists()` 或对应功能接口检查；停用插件后代码无法继续处理短代码。
