<!DOCTYPE html>
<html>
<head>
    <title>CBMS Analytics Dashboard</title>
    <style>
        body, html { margin:0; padding:0; height:100%; background:#f0f2f5; }
        #superset-container { width:100%; height:100vh; }
        .loading { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); color:#333; font-size:18px; z-index:999; }
    </style>
    <script src="http://localhost:8088/static/assets/superset-embedded-sdk.js"></script>
</head>
<body>
    <div class="loading">Loading dashboard with VersionID...</div>
    <div id="superset-container"></div>

    <script>
        // Get VersionID from PHP session
        const versionId = '<?= $_SESSION['cbmsv21']['VersionID'] ?? 'default' ?>';

        // Simple guest token fetch for public dashboards (no auth needed)
        async function fetchGuestToken() {
            const response = await fetch('http://localhost:8088/api/v1/security/guest_token/', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    "user": {
                        "username": "public_guest",
                        "first_name": "Public",
                        "last_name": "Guest"
                    },
                    "resources": [{
                        "type": "dashboard",
                        "id": "1"  // Your dashboard ID
                    }],
                    "rls": []  // No row-level security for public
                })
            });
            const data = await response.json();
            return data.token;
        }

        supersetEmbeddedSdk.embedDashboard({
            id: "1",  // Dashboard ID
            supersetDomain: "http://localhost:8088",
            mountPoint: document.getElementById("superset-container"),
            fetchGuestToken: fetchGuestToken,
            dashboardUiConfig: {
                hideTitle: true,
                filters: { expanded: true }
            },
            urlParams: {
                native_filters: JSON.stringify({
                    "NATIVE_FILTER-ikyKfQn6M9mCNjNLZGRBE": {  // Your VersionID filter ID
                        "value": versionId
                    }
                })
            }
        }).then(() => {
            document.querySelector('.loading').style.display = 'none';
        });
    </script>
</body>
</html>