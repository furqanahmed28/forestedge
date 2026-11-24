<?php require 'layout.php'; ?>

    <h1>Dashboard – Haripur Forest</h1>
    
    <section class="metrics-grid">
      <div class="metric-card">
        <div class="metric-icon" style="background:#fdecea;"><svg width="24" height="24" fill="#c62828" viewBox="0 0 24 24"><path d="M12 2C10 6 6 8.5 6 13a6 6 0 0012 0c0-4.5-4-7-6-11z"/></svg></div>
        <div class="metric-card-content"><h3>New Critical Events</h3><div class="value" id="alert-count">0</div><div class="details">(Requires Action)</div></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="background:#fff8e1;"><svg width="24" height="24" fill="#f9a825" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zM13 18h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></div>
        <div class="metric-card-content"><h3>Nodes Offline</h3><div class="value" id="offline-count">0</div><div class="details">(Connection Lost)</div></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="background:#fdecea;"><svg width="26" height="26" fill="#c62828" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" /></svg></div>
        <div class="metric-card-content"><h3>Highest Risk Cluster</h3><div class="value" id="risk-cluster-value">--</div><div class="details" id="risk-cluster-details">(Active Alerts)</div></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="background:#e3f2fd;"><svg width="24" height="24" fill="#1976D2" viewBox="0 0 24 24"><path d="M12 20a8 8 0 100-16 8 8 0 000 16zm1-8V7h-2v6h6v-2h-4z" /></svg></div>
        <div class="metric-card-content"><h3>Avg. Acknowledge Time</h3><div class="value" id="kpi-ack">--</div><div class="details">(Last 7 Days)</div></div>
      </div>
      
      <div class="metric-card"> 
        <div class="metric-icon" style="background:#e3f2fd;"> <svg width="24" height="24" fill="#0288D1" viewBox="0 0 24 24"> <path d="M15 14.76V5a3 3 0 00-6 0v9.76A4 4 0 1015 14.76zM12 20a2 2 0 01-2-2 2 2 0 011-1.73V5a1 1 0 012 0v11.27A2 2 0 0114 18a2 2 0 01-2 2z"/> </svg> </div> 
        <div class="metric-card-content"><h3>Average Temperature</h3><div class="value" id="kpi-temp">--°C</div><div class="details">(Recent Events Avg)</div></div> 
      </div> 
      
      <div class="metric-card"> 
        <div class="metric-icon" style="background:#e1f5fe;"> <svg width="24" height="24" fill="#039BE5" viewBox="0 0 24 24"> <path d="M12 2C8 6 6 9 6 13a6 6 0 0012 0c0-4-2-7-6-11z" /> </svg> </div> 
        <div class="metric-card-content"><h3>Average Humidity</h3><div class="value" id="kpi-hum">--%</div><div class="details">(Recent Events Avg)</div></div> 
      </div> 
      
      <div class="metric-card"> 
        <div class="metric-icon" style="background:#e8f5e9;"> <svg width="26" height="26" fill="#2E7D32" viewBox="0 0 24 24"> <circle cx="12" cy="12" r="10" /> </svg> </div> 
        <div class="metric-card-content"> <h3>Clusters Active</h3> <div class="value" id="clusters-active-val">1</div> <div class="details">(Current Hardware)</div> </div> 
      </div> 
      
      <div class="metric-card"> 
        <div class="metric-icon" style="background:#fffde7;"> <svg width="26" height="26" fill="none" stroke="#c62828" stroke-width="2" viewBox="0 0 24 24"> <circle cx="12" cy="12" r="9" /> </svg> </div> 
        <div class="metric-card-content" style="width:100%;"> 
          <div style="display:flex; justify-content:space-between; align-items:center;">
              <h3>Total Incidents</h3> 
              <select id="incident-timeframe" onchange="pollData()" style="font-size:0.75rem; border:1px solid #ccc; border-radius:3px; padding:2px;">
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
              </select>
          </div>
          <div class="value" id="events-logged-value">--</div> 
          <div class="details">(Across all nodes)</div> 
        </div> 
      </div>
    </section>

    <section class="panel" style="height: auto; padding: 1.5rem 1.8rem; height: 600px; display:flex; flex-direction:column;">
      <h2 style="font-size: 1.4rem; font-weight: 600; margin-bottom: 1.2rem;">Haripur Forest — Cluster Map</h2>
      <div id="realForestMap" style="flex-grow:1; border-radius: 10px; border: 1px solid var(--border); overflow: hidden; z-index: 10;"></div>
    </section>

    <div class="content-columns">
      <section class="panel" style="height: 720px; overflow-y:auto; padding: 1.5rem 1.8rem;">
        <h2 style="font-size: 1.4rem; font-weight: 600; margin-bottom: 1.2rem;">New Critical Alerts (Action Required)</h2>
        <div class="alert-feed-container" id="alert-feed"></div>
      </section>

      <section class="panel" style="height: 720px; display: flex; flex-direction: column; padding: 1.5rem 1.8rem;">
        <h2 style="font-size: 1.4rem; font-weight: 600; margin-bottom: 1.2rem;">Alert Logs</h2>
        <div style="flex-grow: 1; overflow-y: auto; border-top: 1px solid var(--border);">
          <table class="data-table" id="event-log">
            <thead><tr><th>Event ID</th><th>Time</th><th>Cluster</th><th>Type</th><th>Priority</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>

  <div class="modal-backdrop" id="actionModal">
    <div class="modal-box">
      <h2>Process Event</h2>
      <p style="margin-bottom:1rem; color:#666;" id="modalEventId"></p>
      
      <div class="form-group">
        <label>Action Type</label>
        <select id="actionType" class="form-control">
          <option value="ACK">Acknowledge</option>
          <option value="FALSE-EVENT">Reject / False Alarm</option>
        </select>
      </div>
      <div class="form-group">
        <label>Notes (Optional)</label>
        <textarea id="actionNotes" class="form-control" rows="4" placeholder="Observation notes..."></textarea>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:1rem;">
        <button onclick="closeModal()" class="btn-logout" style="border:1px solid #ccc; background:#eee;">Cancel</button>
        <button onclick="submitAction()" class="btn-primary">Submit</button>
      </div>
    </div>
  </div>

  <script>
    let leafletMap;
    const leafletMarkers = {};
    const nodeColors = { active: "#2E7D32", fire: "#C62828", disease: "#F9A825", other: "#1976D2", offline: "#B0BEC5" };
    let seenAlerts = new Set();
    let currentEventId = null;
    let mapInitialized = false;
    
    function initMap() {
      const center = [34.681852, 72.550841];
      leafletMap = L.map('realForestMap').setView(center, 15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(leafletMap);
    }

    function showToast(message) {
      const tc = document.getElementById('toast-container');
      
      while (tc.children.length >= 3) {
          tc.removeChild(tc.firstChild);
      }
      
      const toast = document.createElement('div');
      toast.className = 'toast show';
      toast.style.position = 'relative';
      toast.style.paddingRight = '30px';
      
      const textNode = document.createTextNode(message);
      toast.appendChild(textNode);
      
      const closeBtn = document.createElement('span');
      closeBtn.innerHTML = '&times;';
      closeBtn.style.position = 'absolute';
      closeBtn.style.right = '10px';
      closeBtn.style.top = '50%';
      closeBtn.style.transform = 'translateY(-50%)';
      closeBtn.style.cursor = 'pointer';
      closeBtn.style.fontSize = '1.2rem';
      closeBtn.style.color = '#fff';
      
      closeBtn.onclick = function() {
          toast.classList.remove('show');
          setTimeout(() => toast.remove(), 300);
      };
      
      toast.appendChild(closeBtn);
      tc.appendChild(toast);
      
      setTimeout(() => {
        if(toast.parentElement) {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }
      }, 4000);
    }

    function openActionModal(eventId) {
      currentEventId = eventId;
      document.getElementById('modalEventId').innerText = "Event ID: " + eventId;
      document.getElementById('actionNotes').value = "";
      document.getElementById('actionType').value = "ACK";
      document.getElementById('actionModal').classList.add('show');
    }
    
    function closeModal() {
      document.getElementById('actionModal').classList.remove('show');
    }

    function submitAction() {
        if(!currentEventId) return;
        const actionType = document.getElementById('actionType').value;
        const notes = document.getElementById('actionNotes').value;
        
        fetch('process_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: currentEventId, action_type: actionType, notes: notes })
        }).then(res => res.json()).then(data => {
            if(data.status === 'success') {
                showToast("Event Processed Successfully!");
                closeModal();
                pollData();
            } else {
                alert("Error: " + data.message);
            }
        });
    }

    function getCritClass(txt) {
        txt = txt.toLowerCase();
        if(txt === 'fire') return 'type-fire';
        if(txt === 'disease') return 'type-disease';
        if(txt === 'high') return 'crit-high';
        if(txt === 'medium') return 'crit-medium';
        return 'crit-low';
    }

    function pollData() {
      fetch('fetch_data.php')
      .then(res => res.json())
      .then(data => {
        let offlineNodes = 0;
        let clusterRiskCount = {};
        let activeClusters = new Set();
        
        data.nodes.forEach(node => {
          if(node.status === "Offline") offlineNodes++;
          activeClusters.add(node.cluster);
          let lat = node.lat || 34.681852;
          let lng = node.lng || 72.550841;

          if (!leafletMarkers[node.id]) {
            const icon = L.divIcon({
                className: 'node-icon sensor-node-icon' + (node.status === 'Offline' ? ' offline-node-icon' : ''),
                html: `<div style="background-color: ${node.status === 'Offline' ? nodeColors.offline : nodeColors.active}; width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">${node.id.substring(0,3)}</div>`,
                iconSize: [30, 30], iconAnchor: [15, 15]
            });
            let marker = L.marker([lat, lng], { icon: icon }).addTo(leafletMap);
            marker.bindPopup(`<b>${node.id}</b><br>Cluster: ${node.cluster}<br>Status: ${node.status}`);
            leafletMarkers[node.id] = marker;
            
            if(!mapInitialized) {
                leafletMap.setView([lat, lng], 16);
                mapInitialized = true;
            }
          } else {
            let m = leafletMarkers[node.id];
            let color = (node.status === 'Offline') ? nodeColors.offline : nodeColors.active;
            let cssClass = 'node-icon sensor-node-icon' + ((node.status === 'Offline') ? ' offline-node-icon' : '');
            m.setIcon(L.divIcon({
                className: cssClass,
                html: `<div style="background-color: ${color}; width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">${node.id.substring(0,3)}</div>`,
                iconSize: [30, 30], iconAnchor: [15, 15]
            }));
          }
        });
        
        document.getElementById('clusters-active-val').textContent = activeClusters.size;
        document.getElementById('offline-count').textContent = offlineNodes;
        document.getElementById('alert-count').textContent = data.alerts.length;
        document.getElementById('kpi-temp').textContent = (data.kpi.avg_temp || '--') + '°C';
        document.getElementById('kpi-hum').textContent = (data.kpi.avg_hum || '--') + '%';
        document.getElementById('kpi-ack').textContent = data.kpi.avg_ack_time || '--';
        
        const timeframe = document.getElementById('incident-timeframe').value;
        document.getElementById('events-logged-value').textContent = timeframe === 'weekly' ? data.kpi.total_weekly : data.kpi.total_monthly;
        
        let highestRiskCluster = "--";
        let maxCount = 0;
        
        // Render Alerts
        const alertFeed = document.getElementById("alert-feed");
        const tbody = document.querySelector("#event-log tbody");
        
        alertFeed.innerHTML = "";
        tbody.innerHTML = "";

        data.alerts.forEach(alert => {
            if(!clusterRiskCount[alert.cluster_id]) clusterRiskCount[alert.cluster_id] = 0;
            clusterRiskCount[alert.cluster_id]++;
            if(clusterRiskCount[alert.cluster_id] > maxCount) {
                maxCount = clusterRiskCount[alert.cluster_id];
                highestRiskCluster = "Cluster " + alert.cluster_id;
            }
            
            if(!seenAlerts.has(alert.alert_id)) {
                showToast("New " + alert.criticality + " Risk Detected: " + alert.problem);
                seenAlerts.add(alert.alert_id);
            }
            
            let probCls = getCritClass(alert.problem);
            let critCls = getCritClass(alert.criticality);
            
            let card = document.createElement('div');
            card.className = "alert-card";
            
            card.innerHTML = `<div class="alert-header">
                                <span class="alert-title ${critCls}">${alert.problem.toUpperCase()} RISK – ${alert.criticality} PRIORITY</span>
                                <button class="btn-primary" style="padding:0.4rem 0.9rem; font-size:0.85rem;" onclick="openActionModal('${alert.alert_id}')">Acknowledge</button>
                              </div>
                              <div class="alert-body">
                                <div class="alert-meta">ID: ${alert.alert_id} | Cluster: ${alert.cluster_id} | Node: ${alert.node_id}</div>
                                <div class="alert-details">${alert.description}</div>
                              </div>`;
            alertFeed.appendChild(card);
            
            let tr = document.createElement('tr');
            let d = new Date(alert.timestamp * 1000);
            const timeString = d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
            const dateString = d.toLocaleString('en-US', { month: 'short', day: '2-digit' });
            tr.innerHTML = `<td><a style="color:var(--green); text-decoration:none; font-weight:bold" href="event_detail.php?id=${alert.alert_id}">${alert.alert_id.substring(0,8)}</a></td><td>${dateString}, ${timeString}</td><td>${alert.cluster_id}</td><td class="${probCls}">${alert.problem}</td><td class="${critCls}">${alert.criticality}</td>`;
            tbody.appendChild(tr);

            if(leafletMarkers[alert.node_id]) {
                let m = leafletMarkers[alert.node_id];
                let color = nodeColors[alert.problem.toLowerCase()] || nodeColors.fire;
                m.setIcon(L.divIcon({
                    className: 'node-icon sensor-node-icon',
                    html: `<div style="background-color: ${color}; width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 10px ${color}; color:white;">${alert.node_id.substring(0,3)}</div>`,
                    iconSize: [36, 36], iconAnchor: [18, 18]
                }));
            }
        });
        
        document.getElementById('risk-cluster-value').textContent = highestRiskCluster;
        if(highestRiskCluster !== "--") {
            document.getElementById('risk-cluster-details').textContent = "(" + maxCount + " alerts)";
        }
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      initMap();
      pollData();
      setInterval(pollData, 5000);
    });
  </script>
  </main>
</body>
</html>
