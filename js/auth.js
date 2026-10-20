// 登录验证通用脚本
(function() {
    // 检查是否已登录
    function checkAuth() {
        const token = localStorage.getItem('token');
        const currentPage = window.location.pathname.split('/').pop();
        
        // 如果当前页面是登录页，不需要检查
        if (currentPage === 'login.html' || currentPage === '') {
            return;
        }
        
        // 如果没有 token，跳转到登录页
        if (!token) {
            window.location.href = 'login.html';
        }
    }
    
    // 页面加载时执行检查
    document.addEventListener('DOMContentLoaded', checkAuth);
    
    // 拦截 axios 响应，处理 401 错误
    if (typeof axios !== 'undefined') {
        axios.interceptors.response.use(
            function(response) {
                // 如果响应码是 401，跳转到登录页
                if (response.data && response.data.code === 401) {
                    localStorage.removeItem('token');
                    window.location.href = 'login.html';
                }
                return response;
            },
            function(error) {
                // 网络错误或其他错误
                return Promise.reject(error);
            }
        );
    }
})();
