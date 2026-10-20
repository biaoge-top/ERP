# 森信 ERP 管理系统

> 面向二手手机回收销售业务的轻量级 ERP 系统，基于 PHP + MySQL + Vue 2 + Element UI。

## 功能特性

- **总览看板**：销售/采购/利润汇总、近 12 个月趋势、Top 排行
- **采购管理**：手机收购录入、成本核算、采购统计
- **质检管理**：待质检列表、一键质检、批量质检
- **销售管理**：上下架管理、销售记录、利润分析
- **手机管理**：全量手机档案检索（品牌/型号/状态/盈亏/日期筛选）
- **快递管理**：采购/销售/售后物流记录，状态联动手机表
- **型号管理**：品牌/系列/型号字典维护
- **企业管理**：多企业（多租户）创建与管理
- **财务管理**：财务统计、明细列表、月度统计
- **系统配置**：颜色/品牌/平台/存储/快递/店铺字典 JSON 配置
- **用户鉴权**：注册、登录、Token 鉴权、权限字段

## 技术栈

| 分层 | 技术 |
| --- | --- |
| 前端 | Vue.js 2.x（CDN）+ Element UI + Axios + ECharts |
| 后端 | PHP（mysqli，预处理语句） |
| 数据库 | MySQL 8（utf8mb4） |
| 鉴权 | 自定义 128 位 Token |

## 快速部署

1. **环境要求**：PHP 7.x+（mysqli 扩展）、MySQL 8+、Web 服务器（Nginx/Apache/宝塔）

2. **导入数据库**

   ```bash
   mysql -u root -p -e "CREATE DATABASE erp DEFAULT CHARSET utf8mb4;"
   mysql -u root -p erp < API/erp.sql
   ```

3. **配置数据库连接**

   ```bash
   cp API/config.example.php API/config.php
   cp API/db.example.php API/db.php
   ```

   编辑 `API/config.php` 和 `API/db.php`，填入真实的数据库地址、用户名、密码。

4. **部署站点**：将项目目录设为站点根目录，访问 `login.html`。

## 默认管理员

| 账号 | 密码 |
| --- | --- |
| `admin` | `admin123` |

> 密码以 MD5 存储于 `users` 表。**部署后请立即登录并修改默认密码。**

## 目录结构

```
├── index.html              # 主框架（左侧菜单 + iframe 加载子页面）
├── login.html              # 登录注册页
├── overview.html           # 总览页
├── purchase.html           # 采购管理页
├── quality.html            # 质检管理页
├── sell.html               # 销售管理页
├── phone.html              # 手机管理页
├── express.html            # 快递管理页
├── model.html              # 型号管理页
├── company.html            # 企业管理页
├── finance.html            # 财务管理页
├── js/                     # 前端公共脚本
├── style/                  # 样式与前端库
└── API/                    # 后端 PHP 接口
    ├── erp.sql             # 数据库结构 + 默认管理员
    ├── config.example.php  # 全局配置模板
    └── db.example.php      # 数据库连接模板
```

详细开发文档见 [开发文档.md](开发文档.md)。

## 开源协议

本项目基于 [GPL-3.0](LICENSE) 协议开源。
