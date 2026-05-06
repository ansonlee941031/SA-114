// js/map.js

// 確保網頁載入完成後再執行地圖渲染
document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map', { scrollWheelZoom: true }).setView([25.035, 121.445], 15);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    // 接收從 PHP 傳過來的全域變數
    var cafes = window.cafeData || [];
    var markers = [];

    if (cafes.length > 0) {
        cafes.forEach(function(cafe) {
            
            // 依照營業狀態決定顏色
            var pinColorClass = 'pin-closed'; // 預設為灰色 (已打烊)
            var statusText = '<span style="color:#7f8c8d; font-weight:bold;">○ 已打烊</span>'; // 彈窗內的文字也改成灰色

            if (cafe.isOpen) {
                pinColorClass = 'pin-open';  // 營業中變為綠色
                statusText = '<span style="color:#27ae60; font-weight:bold;">● 營業中</span>';
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
                        🕒 營業時間：<br>${cafe.opening_hours ? cafe.opening_hours.replace(/\r\n|\n/g, '<br>') : '暫無資訊'}
                    </div>
                    <button onclick="scrollToCafe(${cafe.id})" style="margin-top:8px; background:#8d6e63; color:white; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; width:100%;">查看詳細卡片</button>
                </div>
            `;

            var marker = L.marker([cafe.lat, cafe.lng], { icon: icon }).addTo(map).bindPopup(popupContent);
            markers.push(marker);
        });
        
        // 自動縮放地圖以包含所有標記 (這段剛剛漏掉了)
        var group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}); // <-- 剛剛漏掉了這個閉合括號

// 將點擊滾動功能註冊為全域變數，這樣彈窗內的按鈕才抓得到
window.scrollToCafe = function(id) {
    document.querySelectorAll('.card').forEach(c => c.classList.remove('highlight-card'));
    var target = document.getElementById('cafe-' + id);
    if (target) {
        target.classList.add('highlight-card');
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};