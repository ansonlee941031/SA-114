// js/map.js

// 確保網頁載入完成後再執行地圖渲染
document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map', { scrollWheelZoom: true }).setView([25.035, 121.445], 15);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    // 接收從 PHP 傳過來的全域變數 (來自 cafe_map.php)
    var cafes = window.cafeData || [];
    var markers = [];

    // 將 "HH:mm:ss" 轉為總分鐘數的輔助函式
    function timeToMinutes(timeStr) {
        if (!timeStr || timeStr === 'NULL') return null;
        var parts = timeStr.split(':');
        return parseInt(parts[0]) * 60 + parseInt(parts[1]);
    }

    if (cafes.length > 0) {
        cafes.forEach(function(cafe) {
            // --- 核心：時間與顏色判斷邏輯 ---
            var now = new Date();
            var currentMinutes = now.getHours() * 60 + now.getMinutes();
            
            var openMin = timeToMinutes(cafe.open_time); 
            var closeMin = timeToMinutes(cafe.close_time);

            var pinColorClass = 'pin-closed'; // 預設灰色
            var statusText = '<span style="color:#7f8c8d; font-weight:bold;">○ 已打烊</span>';

            if (cafe.isOpen) {
                // 1. 營業中 - 預設綠色
                pinColorClass = 'pin-open';
                statusText = '<span style="color:#27ae60; font-weight:bold;">● 營業中</span>';

                // 2. 判斷是否為「閉店前 30 分鐘內」 - 變橘色
                if (closeMin && (closeMin - currentMinutes) <= 30 && (closeMin - currentMinutes) > 0) {
                    pinColorClass = 'pin-closing-soon';
                    statusText = '<span style="color:#e67e22; font-weight:bold;">● 即將打烊</span>';
                }
            } else {
                // 3. 非營業時間 - 判斷是否為「開店前 30 分鐘內」 - 變黃色
                // 條件：今天不是公休 (is_closed 為 0 或 false)
                if (cafe.is_closed != 1 && openMin && (openMin - currentMinutes) <= 30 && (openMin - currentMinutes) > 0) {
                    pinColorClass = 'pin-opening-soon';
                    statusText = '<span style="color:#f1c40f; font-weight:bold;">○ 即將營業</span>';
                }
            }

            // 建立地圖圖示
            var icon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div class='marker-pin ${pinColorClass}'></div>`,
                iconSize: [30, 42],
                iconAnchor: [15, 42]
            });

            // js/map.js 修正彈窗內容部分

// ... 之前的顏色判斷邏輯保持不變 ...

// 彈窗內容
var popupContent = `
    <div style="font-family: sans-serif; min-width: 150px;">
        <b style="color:#8d6e63; font-size:14px;">${cafe.name}</b><br>
        <span style="color:#666; font-size:12px;">${cafe.address}</span><br>
        <div style="margin-top: 5px;">${statusText}</div>
        <div style="font-size:11px; color:#444; margin-top:5px; border-top:1px solid #eee; padding-top:5px;">
            🕒 今日營業時間：<br>${cafe.today_hours} 
        </div>
        <button onclick="scrollToCafe(${cafe.id})" style="margin-top:8px; background:#8d6e63; color:white; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; width:100%;">查看詳細卡片</button>
    </div>
`;

// ... 後續 marker 生成 ...

            var marker = L.marker([cafe.lat, cafe.lng], { icon: icon }).addTo(map).bindPopup(popupContent);
            markers.push(marker);
        });
        
        // 自動縮放地圖以包含所有標記
        if (markers.length > 0) {
            var group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
});

// 將點擊滾動功能註冊為全域變數
window.scrollToCafe = function(id) {
    document.querySelectorAll('.card').forEach(c => c.classList.remove('highlight-card'));
    var target = document.getElementById('cafe-' + id);
    if (target) {
        target.classList.add('highlight-card');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};