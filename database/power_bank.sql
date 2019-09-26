/*
 Navicat Premium Data Transfer

 Source Server         : 腾讯云充电宝
 Source Server Type    : MySQL
 Source Server Version : 50727
 Source Host           : 123.207.32.154:3306
 Source Schema         : power_bank

 Target Server Type    : MySQL
 Target Server Version : 50727
 File Encoding         : 65001

 Date: 26/09/2019 16:29:33
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for i_auths
-- ----------------------------
DROP TABLE IF EXISTS `i_auths`;
CREATE TABLE `i_auths`  (
  `auth_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '权限id',
  `auth_name` varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '权限名',
  `auth_rules` varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '路由地址',
  `auth_icon` varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '权限图标',
  `auth_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '权限类型 0菜单1按钮',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`auth_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 27 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '权限表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of i_auths
-- ----------------------------
INSERT INTO `i_auths` VALUES (1, '后台管理', 'admin/*/*', '', 0, 0, 0);
INSERT INTO `i_auths` VALUES (2, '权限管理', 'admin/auth/lists', '', 0, 0, 0);
INSERT INTO `i_auths` VALUES (3, '角色管理', 'admin/role/lists', '', 0, 0, 0);
INSERT INTO `i_auths` VALUES (4, '用户管理', 'admin/user/lists', '', 0, 0, 0);

-- ----------------------------
-- Table structure for i_migrations
-- ----------------------------
DROP TABLE IF EXISTS `i_migrations`;
CREATE TABLE `i_migrations`  (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `start_time` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of i_migrations
-- ----------------------------
INSERT INTO `i_migrations` VALUES (20190422152409, 'User', '2019-04-23 10:33:31', '2019-04-23 10:33:31', 0);
INSERT INTO `i_migrations` VALUES (20190422153129, 'Auth', '2019-04-23 10:33:31', '2019-04-23 10:33:31', 0);
INSERT INTO `i_migrations` VALUES (20190422153557, 'Role', '2019-04-23 10:33:32', '2019-04-23 10:33:32', 0);
INSERT INTO `i_migrations` VALUES (20190422153927, 'System', '2019-04-23 10:33:32', '2019-04-23 10:33:32', 0);
INSERT INTO `i_migrations` VALUES (20190423082658, 'UserChange', '2019-04-23 16:29:56', '2019-04-23 16:29:57', 0);
INSERT INTO `i_migrations` VALUES (20190423083012, 'UserAddAuth', '2019-04-23 16:32:08', '2019-04-23 16:32:10', 0);

-- ----------------------------
-- Table structure for i_roles
-- ----------------------------
DROP TABLE IF EXISTS `i_roles`;
CREATE TABLE `i_roles`  (
  `role_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `role_name` varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '角色名',
  `role_auth` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '角色权限',
  `role_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '角色状态 0正常1禁用',
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '角色级别 越小权限越高',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`role_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '角色表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of i_roles
-- ----------------------------
INSERT INTO `i_roles` VALUES (1, '管理员', '1,2,3,4,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26', 0, 0, 0, 0);

-- ----------------------------
-- Table structure for i_system
-- ----------------------------
DROP TABLE IF EXISTS `i_system`;
CREATE TABLE `i_system`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_next_id` int(11) NOT NULL COMMENT '下一个用户id',
  `auth_order` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '[]' COMMENT '权限排序',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '系统参数杂项' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of i_system
-- ----------------------------
INSERT INTO `i_system` VALUES (1, 10052, '[{\"id\":1,\"child\":[{\"id\":2},{\"id\":3},{\"id\":4}]}]');

-- ----------------------------
-- Table structure for i_users
-- ----------------------------
DROP TABLE IF EXISTS `i_users`;
CREATE TABLE `i_users`  (
  `u_id` int(11) NOT NULL AUTO_INCREMENT,
  `u_password` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'e10adc3949ba59abbe56e057f20f883e' COMMENT '用户密码',
  `u_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '用户名',
  `u_account` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户登录名',
  `p_u_id` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '上级u_id',
  `role_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `u_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户状态 -1删除 0禁用 1正常',
  `u_level_line` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0-' COMMENT '用户层级链',
  `u_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户签名所用到的key值',
  `last_login_ip` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录IP',
  `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `u_auth` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`u_id`) USING BTREE,
  UNIQUE INDEX `u_id`(`u_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 59 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '用户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of i_users
-- ----------------------------
INSERT INTO `i_users` VALUES (1, 'e35cf7b66449df565f93c607d5a81d09', 'Siam', '1001', '0', '1', 1, '0-1', '021A57F3965BF5C2DC40BB8753D28B56', 0, 0, 0, 1569486465, '');

SET FOREIGN_KEY_CHECKS = 1;
