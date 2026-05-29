// js/map.js 完整版 (支援 AJAX 動態重繪)
document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map', { scrollWheelZoom: true }).setView([25.035, 121.445], 15);
    
    // 彩色版 OSM 地圖
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // 新增：建立一個專門放標記的圖層群組，方便一鍵清除
    var markerGroup = L.layerGroup().addTo(map);
    var markerMap = {}; 
    var markerList = []; 

    // 將繪製標記的邏輯打包成全域函數，讓 AJAX 可以隨時呼叫它
    window.updateMapMarkers = function(cafes) {
        markerGroup.clearLayers(); // 💡 核心：清除地圖上舊的標記
        markerMap = {};
        markerList = [];

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

                // 綁定圖標並加入 markerGroup 群組中
                var marker = L.marker([cafe.lat, cafe.lng], { icon: icon }).bindPopup(popupContent);
                markerGroup.addLayer(marker); 
                
                markerMap[cafe.id] = marker;
                markerList.push(marker);
            });

            if (window.targetCafeId && markerMap[window.targetCafeId]) {
                var target = markerMap[window.targetCafeId];
                map.setView(target.getLatLng(), 17);
                target.openPopup();
                setTimeout(() => { window.scrollToCafe(window.targetCafeId); }, 600);
            } else if (markerList.length > 0) {
                var group = new L.featureGroup(markerList);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
    };

    // 初始載入時，執行第一次繪製
    window.updateMapMarkers(window.cafeData || []);
});

// --- 全域滾動函式 ---
window.scrollToCafe = function(id) {
    document.querySelectorAll('.card').forEach(c => c.classList.remove('highlight-card'));
    var target = document.getElementById('cafe-' + id);
    if (target) {
        target.classList.add('highlight-card');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.style.transition = "all 0.5s";
        target.style.boxShadow = "0 0 20px rgba(141, 110, 99, 0.5)";
        setTimeout(() => { target.style.boxShadow = ""; }, 2000);
    }
};

// --- 收藏功能函式 ---
async function toggleFav(cafeId, btn) {
    const icon = btn.querySelector('i');
    const isAdding = icon.classList.contains('fa-regular'); 
    const action = isAdding ? 'add' : 'remove';

    try {
        const response = await fetch('api_favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, cafe_id: cafeId })
        });
        const data = await response.json();
        if (data.status === 'success') {
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