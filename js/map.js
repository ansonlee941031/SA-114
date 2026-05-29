// js/map.js 完整版

document.addEventListener("DOMContentLoaded", function() {
    // 初始化地圖
    var map = L.map('map', { scrollWheelZoom: true }).setView([25.035, 121.445], 15);
    
    // 🟢 已經更換為：OpenStreetMap 標準彩色底圖 (路網鮮明、公園呈綠色、河流呈藍色)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var cafes = window.cafeData || [];
    var markerMap = {}; // 用於 ID 檢索
    var markerList = []; // 用於自動縮放

    function timeToMinutes(timeStr) {
        if (!timeStr || timeStr === 'NULL') return null;
        var parts = timeStr.split(':');
        return parseInt(parts[0]) * 60 + parseInt(parts[1]);
    }

    if (cafes.length > 0) {
        cafes.forEach(function(cafe) {
            var now = new Date();
            var currentMinutes = now.getHours() * 60 + now.getMinutes();
            var openMin = timeToMinutes(cafe.open_time); 
            var closeMin = timeToMinutes(cafe.close_time);

            var pinColorClass = 'pin-closed'; 
            var statusText = '<span style="color:#7f8c8d; font-weight:bold;">○ 已打烊</span>';

            // 營業狀態判斷
            if (cafe.isOpen) {
                pinColorClass = 'pin-open';
                statusText = '<span style="color:#27ae60; font-weight:bold;">● 營業中</span>';
                if (closeMin && (closeMin - currentMinutes) <= 30 && (closeMin - currentMinutes) > 0) {
                    pinColorClass = 'pin-closing-soon';
                    statusText = '<span style="color:#e67e22; font-weight:bold;">● 即將打烊</span>';
                }
            } else if (cafe.is_closed != 1 && openMin && (openMin - currentMinutes) <= 30 && (openMin - currentMinutes) > 0) {
                pinColorClass = 'pin-opening-soon';
                statusText = '<span style="color:#f1c40f; font-weight:bold;">○ 即將營業</span>';
            }

            var icon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div class='marker-pin ${pinColorClass}'></div>`,
                iconSize: [30, 42],
                iconAnchor: [15, 42]
            });

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

            var marker = L.marker([cafe.lat, cafe.lng], { icon: icon }).addTo(map).bindPopup(popupContent);
            
            // 儲存標記
            markerMap[cafe.id] = marker;
            markerList.push(marker);
        });

        // --- 核心：執行自動跳轉與定位 ---
        if (window.targetCafeId && markerMap[window.targetCafeId]) {
            var target = markerMap[window.targetCafeId];
            
            // 1. 移動地圖視角
            map.setView(target.getLatLng(), 17);
            
            // 2. 開啟氣泡窗
            target.openPopup();
            
            // 3. 延遲一下再滾動卡片，確保 DOM 渲染與地圖移動穩定
            setTimeout(() => {
                window.scrollToCafe(window.targetCafeId);
            }, 600);

        } else if (markerList.length > 0) {
            // 若無特定 ID，則縮放至包含所有標記
            var group = new L.featureGroup(markerList);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
});

// 全域滾動函式
window.scrollToCafe = function(id) {
    // 移除其他卡片的高亮
    document.querySelectorAll('.card').forEach(c => c.classList.remove('highlight-card'));
    
    var target = document.getElementById('cafe-' + id);
    if (target) {
        target.classList.add('highlight-card');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // 增加一個短暫的視覺特效
        target.style.transition = "all 0.5s";
        target.style.boxShadow = "0 0 20px rgba(141, 110, 99, 0.5)";
        setTimeout(() => { target.style.boxShadow = ""; }, 2000);
    }
};

// 收藏功能明細 (修正先前被省略的部分)
async function toggleFav(cafeId, btn) {
    const icon = btn.querySelector('i');
    const isAdding = icon.classList.contains('fa-regular'); // 判斷目前是空心還是實心
    const action = isAdding ? 'add' : 'remove';

    try {
        const response = await fetch('api_favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, cafe_id: cafeId })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // 切換圖示
            if (isAdding) {
                icon.classList.replace('fa-regular', 'fa-solid');
                icon.style.color = '#ff4d4d';
            } else {
                icon.classList.replace('fa-solid', 'fa-regular');
                icon.style.color = '#ccc';
            }
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
