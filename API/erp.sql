DROP TABLE IF EXISTS `companys`;
CREATE TABLE `companys` (
  `cid` int NOT NULL AUTO_INCREMENT COMMENT '企业ID（主键自增）',
  `company_name` varchar(100) NOT NULL COMMENT '企业名称',
  `uid` int NOT NULL DEFAULT '0' COMMENT '管理员用户ID',
  `vip_date` varchar(20) NOT NULL DEFAULT '0' COMMENT '会员到期时间 0=无会员',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=关闭 1=正常',
  PRIMARY KEY (`cid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='企业信息表';

-- ----------------------------
-- Table structure for express
-- ----------------------------
DROP TABLE IF EXISTS `express`;
CREATE TABLE `express` (
  `express_id` int NOT NULL AUTO_INCREMENT COMMENT '快递ID',
  `wid` int NOT NULL COMMENT '商品ID（手机回收表ID）',
  `courier_company` varchar(50) DEFAULT NULL COMMENT '快递公司',
  `tracking_id` varchar(50) DEFAULT NULL COMMENT '快递单号',
  `express_type` varchar(20) DEFAULT NULL COMMENT '快递类型 采购/销售/售后(回)/售后(发)',
  `postage` decimal(10,2) DEFAULT '0.00' COMMENT '邮费',
  `delivery_time` varchar(20) DEFAULT NULL COMMENT '发货时间',
  `courier_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=关闭 1=正常',
  PRIMARY KEY (`express_id`),
  KEY `idx_wid` (`wid`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='快递物流表';

-- ----------------------------
-- Table structure for phone
-- ----------------------------
DROP TABLE IF EXISTS `phone`;
CREATE TABLE `phone` (
  `wid` int NOT NULL AUTO_INCREMENT COMMENT '序号（主键，自增）',
  `machine_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '机器编码',
  `acquisition_time` varchar(20) DEFAULT NULL COMMENT '收购时间',
  `brand` varchar(30) DEFAULT NULL COMMENT '品牌',
  `series` varchar(30) DEFAULT NULL COMMENT '系列',
  `model` varchar(50) DEFAULT NULL COMMENT '型号',
  `storage` varchar(30) DEFAULT NULL COMMENT '存储配置',
  `color` varchar(20) DEFAULT NULL COMMENT '颜色',
  `battery` int DEFAULT '100' COMMENT '电池健康/数值',
  `acquisition_platform` char(20) DEFAULT NULL COMMENT '收购平台',
  `purchase_price` decimal(10,2) DEFAULT NULL COMMENT '收购价',
  `repair` decimal(10,2) DEFAULT '0.00' COMMENT '维修费用',
  `matching` decimal(10,2) DEFAULT '0.00' COMMENT '配套费用',
  `cost` decimal(10,2) DEFAULT NULL COMMENT '总成本',
  `postage` decimal(10,2) DEFAULT '0.00' COMMENT '邮费',
  `selling_price` decimal(10,2) DEFAULT NULL COMMENT '售价',
  `sell_time` varchar(20) DEFAULT NULL COMMENT '出售时间',
  `profit` decimal(10,2) DEFAULT NULL COMMENT '利润',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `state` int DEFAULT '3',
  `shop_id` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  PRIMARY KEY (`wid`)
) ENGINE=InnoDB AUTO_INCREMENT=286 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='二手手机回收销售明细记录表';

-- ----------------------------
-- Table structure for shops
-- ----------------------------
DROP TABLE IF EXISTS `shops`;
CREATE TABLE `shops` (
  `sid` int NOT NULL AUTO_INCREMENT COMMENT '店铺ID（主键自增）',
  `cid` int NOT NULL COMMENT '企业ID',
  `shop_name` varchar(100) NOT NULL COMMENT '店铺名称',
  `platform` varchar(50) DEFAULT NULL COMMENT '运营平台 闲鱼/拍机堂等',
  `uid` int NOT NULL DEFAULT '0' COMMENT '管理员用户ID',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=关闭 1=正常',
  PRIMARY KEY (`sid`),
  KEY `idx_cid` (`cid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='企业店铺表';

-- ----------------------------
-- Table structure for systems
-- ----------------------------
DROP TABLE IF EXISTS `systems`;
CREATE TABLE `systems` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `cid` int NOT NULL COMMENT '企业ID',
  `color_system` text COMMENT '颜色配置 JSON格式',
  `brand_system` text COMMENT '品牌+系列配置 JSON格式',
  `platform_system` text COMMENT '平台配置 JSON格式',
  `storage_system` text COMMENT '存储配置 JSON格式',
  `express_system` text COMMENT '快递公司配置 JSON格式',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cid` (`cid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='企业系统配置表';

-- ----------------------------
-- Table structure for tokens
-- ----------------------------
DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `uid` int NOT NULL COMMENT '用户ID',
  `cid` int NOT NULL COMMENT '企业ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `token` varchar(128) NOT NULL COMMENT '128位随机Token密文',
  `authority` varchar(50) DEFAULT NULL COMMENT '用户权限',
  `expiration_time` varchar(30) DEFAULT NULL COMMENT '过期时间',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=失效 1=正常',
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户登录Token表';

-- ----------------------------
-- Table structure for types
-- ----------------------------
DROP TABLE IF EXISTS `types`;
CREATE TABLE `types` (
  `tid` int NOT NULL AUTO_INCREMENT COMMENT '型号ID【主键自增，修正重复uid】',
  `cid` int NOT NULL COMMENT '企业ID',
  `brand` varchar(30) NOT NULL COMMENT '品牌 HUAWEI,REDMI',
  `series` varchar(30) NOT NULL COMMENT '系列 P,K',
  `model` varchar(50) NOT NULL COMMENT '型号 40 PRO+,40',
  `uid` int NOT NULL COMMENT '创建用户ID',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=关闭 1=正常',
  PRIMARY KEY (`tid`),
  KEY `idx_cid` (`cid`),
  KEY `idx_brand` (`brand`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='手机型号配置表';

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `uid` int NOT NULL AUTO_INCREMENT COMMENT '用户ID（主键自增）',
  `cid` int NOT NULL COMMENT '企业ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(100) NOT NULL COMMENT '密码 MD5加盐加密',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱号',
  `mobile_phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `authority` varchar(50) DEFAULT NULL COMMENT '权限 采购员/质检员/销售员等',
  `register_time` varchar(20) DEFAULT NULL COMMENT '注册时间',
  `register_ip` varchar(50) DEFAULT NULL COMMENT '注册IP',
  `state` tinyint NOT NULL DEFAULT '1' COMMENT '状态 0=关闭 1=正常',
  PRIMARY KEY (`uid`),
  KEY `idx_cid` (`cid`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='系统用户表';

-- ----------------------------
-- 默认管理员账号：admin / admin123（密码为 MD5 加密，部署后请尽快修改）
-- ----------------------------
INSERT INTO `users` (`uid`, `cid`, `username`, `password`, `authority`, `state`) VALUES (1, 0, 'admin', '0192023a7bbd73250516f069df18b500', '管理员', 1);
