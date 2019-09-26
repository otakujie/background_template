/**

 @Name：jwt 解码取值
 @Author：Siam
 @Site：

 */

layui.define('form', function(exports){
    var $ = layui.$
        ,layer = layui.layer
        ,laytpl = layui.laytpl
        ,setter = layui.setter
        ,view = layui.view
        ,admin = layui.admin
        ,form = layui.form;

    var siam = {
        get: function(str){
            var token = layui.data(setter.tableName)[setter.request.tokenName];
            if (token===undefined){
                return null;
            }
            var tokenArr = token.split(".");
            var tokenData = tokenArr[1];
            tokenData = tokenData.replace(/_b_/g,"=");
            tokenData = tokenData.replace(/_a_/g,"+");
            tokenData = tokenData.replace(/_/g,"/");
            var json =window.atob(tokenData);
            var obj = JSON.parse(json);
            var timestamp = Date.parse(new Date())/1000;

            // 在此之前不可用
            if (obj.nbf !== undefined && obj.nbf > timestamp){
                return 'NOTBEFORE';
            }
            // 是否已经过期
            if (obj.exp !== undefined && obj.exp < timestamp){
                return 'EXP';
            }
            
            return obj[str];
        }
    };


    //对外暴露的接口
    exports('siam', siam);
});