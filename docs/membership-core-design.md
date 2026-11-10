# 会员主要业务设计

## 已确认范围

成长计划默认开放，成长值持续累计，不做退款冲回、年度保级和成长值过期。
套餐只授予限时等级资格，不单独提供一套权益。
当前等级取成长等级与有效付费等级中较高者。
同级续费保留剩余时长，购买与续费沿用现有订阅流程。

## 官方资料

- [有赞会员等级](https://help.youzan.com/displaylist/detail_4_4-2-21541)：按消费或成长值分层，允许选择不降级。
- [有赞免费与付费等级](https://help.youzan.com/displaylist/detail_4_4-1-48014)：免费与付费模式可以同时启用。
- [有赞付费会员](https://help.youzan.com/displaylist/detail_4_4-2-41725)：价格与有效期组成售卖规则，续费延长有效期。
- [有赞多等级处理](https://help.youzan.com/displaylist/detail_4_4-1-36878)：跨级购买涉及冻结与剩余时长管理，超过首期范围。
- [Smile VIP](https://smile.io/vip)：按消费或获得的积分升级，支持自然年或终身累计窗口。
- [WooCommerce Membership Plans](https://woocommerce.com/document/woocommerce-memberships-plans/)：资格来源、套餐与有效期分别表达。
- [Shopify Subscriptions](https://shopify.dev/docs/apps/build/purchase-options/subscriptions)：售卖方案与客户持有的合同分离。

这些系统的具体政策不同，本项目采用上述已确认范围，不声称它们全部使用同一套规则。

## 代码边界

沿用现有 Loyalty、Membership、DAL 和共享 PurchaseSettlement。
LoyaltyLedger 负责积分与成长记账，TierResolver 负责当前等级。
MembershipSubscriptionService 只维护订阅事实和付费资格投影。
新增 paidTierId 区分付费资格与最终 tierId，避免两个模块覆盖同一事实。
读取当前权益时检查有效期，定时任务只负责收尾与记录结束状态。

沿用现有目录、命名与扩展边界，不新增统一会员引擎或额外策略层。

| 位置 | 职责与复用方式 |
| --- | --- |
| `Loyalty/Service/TierResolver` | 集中计算成长等级、有效付费等级及最终等级，复用现有等级变更事件 |
| `Membership/Service/MembershipSubscriptionService` | 管理开通、续期、撤销和到期，向成长账户投影付费资格 |
| `Membership/Purchase` | 延续购买快照与共享 PurchaseSettlement 的结算幂等机制 |
| `Loyalty/SalesChannel`、`Loyalty/Api` | 通过同一等级解析器返回当前等级，避免到期任务延迟造成显示与权益不一致 |
| `sw-settings-membership` | 保留现有等级和套餐列表、详情、创建页面，补齐菜单声明 |

后台沿用客户群模块的列表、详情、创建与菜单注册方式。
套餐和等级同时声明前端 navigation 与核心菜单，角色菜单权限保持独立于 API ACL。
首期复用已有等级权益模型和支付流程。

## 验证与迁移

覆盖默认成长、成长超过付费等级、到期读取、同级续费、撤销资格、菜单注册和套餐校验。
增量迁移保留现有成长余额，付费资格从订阅记录回填，重复执行不覆盖后续变更。
保留现有 API 字段、ACL 名称与 Twig 扩展块。
