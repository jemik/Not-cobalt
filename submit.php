<?php
/**
 * Cobalt Strike C2 Server Endpoint Simulator
 * 
 * This PHP script simulates a Cobalt Strike C2 server endpoint that receives
 * beacon check-ins and returns appropriate responses. Use this for testing
 * NDR solutions in a controlled environment.
 * 
 * Usage: Deploy on a web server (Apache/Nginx with PHP) or use PHP's built-in server:
 *   php -S 0.0.0.0:8080 submit.php
 */

// Handle GET requests - show the web interface
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Not a teamsserver</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        #mesh-bg {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            display: block;
            z-index: 0;
            pointer-events: none;
            background: #ffffff;
        }
        
        header {
            height: 70px;
            background: #ffffff;
            padding: 0 40px;
            border-bottom: 1px solid #e0e0e0;
            z-index: 2;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        header svg {
            flex-shrink: 0;
        }
        
        header h1 {
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        nav {
            background: #212121;
            padding: 0 40px;
            height: 50px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #333333;
            z-index: 2;
        }
        
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            align-items: center;
        }
        
        nav .nav-text {
            color: #ffffff;
            font-size: 11px;
            letter-spacing: 0px;
            text-transform: uppercase;
        }
        
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .terminal {
            width: 100%;
            max-width: 800px;
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }
        
        .terminal-header {
            background: #2a2a2a;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .terminal-button {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .terminal-button.close { background: #ff5f56; }
        .terminal-button.minimize { background: #ffbd2e; }
        .terminal-button.maximize { background: #27c93f; }
        
        .terminal-title {
            flex: 1;
            text-align: center;
            color: #8a8a8a;
            font-size: 13px;
        }
        
        .terminal-content {
            padding: 24px;
            font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            color: #d4d4d4;
        }
        
        .prompt {
            color: #4fc3f7;
        }
        
        .path {
            color: #00afff;
        }
        
        .command {
            color: #ffffff;
        }
        
        .comment {
            color: #8a8a8a;
        }
        
        .output {
            color: #d4d4d4;
            margin: 12px 0;
        }
        
        .highlight {
            color: #4fc3f7;
        }
        
        .code-block {
            background: #0a0a0a;
            padding: 16px;
            border-radius: 4px;
            margin: 16px 0;
            border-left: 3px solid #4fc3f7;
        }
        
        .code-block code {
            display: block;
            color: #d4d4d4;
            word-break: break-all;
        }
        
        footer {
            background: #fff;
            padding: 40px 40px;
            border-top: 1px solid #e0e0e0;
            z-index: 3;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
        }
        
        .footer-left {
            flex: 1;
            text-align: left;
            max-width: 405px
        }
        
        .footer-right {
            text-align: right;
            color: #555;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .version {
            color: #000;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .disclaimer {
            color: #555;
            font-size: 11px;
            line-height: 1.5;
        }
        
        .disclaimer strong {
            color: #ff5f56;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <svg width="30" height="30" viewBox="0 0 182 191" xmlns="http://www.w3.org/2000/svg">
                <path id="Shape" fill="#000000" fill-rule="evenodd" stroke="none" d="M 87 175 C 67.644447 175 60.073181 174.673187 59.200001 173.800003 C 58.428017 173.028015 58 169.063019 58 162.683594 C 58 152.767181 58 152.767181 51.235508 146.133591 C 47.391846 142.364304 43.181908 137.002991 41.485508 133.717041 C 38.5 127.934074 38.5 127.934074 41 123.56575 C 42.375 121.163177 48.856926 110.940514 55.404278 100.848717 C 61.951633 90.75692 68.196037 81.706795 69.280731 80.737312 C 70.365417 79.767838 72.368477 78.694641 73.731972 78.352425 C 75.185249 77.987671 77.620148 78.402168 79.616478 79.354156 C 81.660477 80.328865 83.698845 82.397636 84.715157 84.52887 C 86.045273 87.318146 86.179398 88.660889 85.340569 90.789825 C 84.75325 92.280426 81.736359 97.751892 78.63636 102.948647 C 73.017464 112.368019 73 112.422646 73 120.576202 C 73 126.595268 73.392746 129.081055 74.487267 129.989426 C 75.30526 130.668304 76.655258 130.962524 77.487267 130.643265 C 78.557617 130.232529 79 128.605087 79 125.078178 C 79 120.197784 79.088837 120.051323 83.25 118.071693 C 85.587502 116.959656 88.207184 115.52906 89.071518 114.892601 C 90.327011 113.968102 91.47963 114.289024 94.803215 116.488472 C 97.112671 118.0168 100.942986 119.464676 103.413254 119.743111 C 106.726143 120.116524 109.033997 119.677864 112.445694 118.026291 C 115.714409 116.443932 117.812668 114.473579 119.764137 111.153954 C 122.490959 106.515373 122.501175 106.425667 122.856331 84 C 123.150581 65.420082 122.937622 60.706177 121.63401 56.94371 C 120.227028 52.882904 120.206154 51.090881 121.442078 40.464798 C 122.770454 29.043808 122.964638 28.420792 126.057213 25.657578 C 128.922607 23.097336 129.843399 22.826584 134.246109 23.249634 C 138.182251 23.627838 139.694107 24.322418 141.567337 26.613129 C 143.411438 28.868225 143.935944 30.730057 143.964035 35.120712 C 143.98381 38.212097 143.326538 47.212097 142.503418 55.120712 C 141.680298 63.02932 140.330292 77.582588 139.503418 87.461296 C 138.676544 97.340012 138 111.382042 138 118.66581 C 138 130.22702 137.687515 132.772095 135.539627 138.704514 C 133.605164 144.047485 131.941956 146.568726 127.758392 150.5 C 124.831917 153.25 120.989082 156.266571 119.218765 157.203491 C 116.073044 158.868317 116 159.062347 116 165.753494 C 116 169.745224 115.499641 173.100357 114.800003 173.800003 C 113.926819 174.673187 106.355553 175 87 175 Z M 37.121319 116 C 36.674347 116 36.023655 110.487503 35.675331 103.75 C 35.327011 97.012497 34.577999 85.650002 34.01086 78.5 C 33.443726 71.349998 32.550312 61.449997 32.025505 56.5 C 31.500694 51.550003 30.767567 42.717926 30.396332 36.873169 C 30.025097 31.028397 30.027254 25.027542 30.401123 23.537933 C 30.774994 22.048309 32.194084 19.797867 33.554657 18.536926 C 34.91523 17.275986 37.400013 15.970001 39.076397 15.63472 C 40.827385 15.284515 43.554146 15.622528 45.484406 16.429031 C 47.332424 17.201187 49.507488 18.883041 50.317886 20.166473 C 51.128281 21.449921 52.737549 27.899994 53.894035 34.5 C 55.050518 41.100006 56.931599 51.899994 58.074211 58.5 C 59.216824 65.099998 60.396355 72.773743 60.695389 75.552757 C 61.209145 80.327209 60.967152 81.015686 56.301781 88.052757 C 53.586262 92.148743 48.342613 100.112503 44.649231 105.75 C 40.955849 111.387497 37.568287 116 37.121319 116 Z M 106.24572 113.49305 C 104.735573 113.735786 102.73967 113.611649 101.810379 113.217194 C 100.881088 112.822739 98.743591 111.178307 97.060379 109.562912 C 94 106.625816 94 106.625816 94 83.035416 C 94 62.802322 94.234299 59.09314 95.646301 56.972504 C 96.551773 55.612625 97.987778 53.9375 98.837433 53.25 C 99.687088 52.5625 102.433754 52.014313 104.941132 52.031799 C 107.448509 52.049301 110.391022 52.737106 111.480049 53.560257 C 112.569069 54.383423 114.015625 56.05661 114.694611 57.278458 C 115.569275 58.852432 115.939461 66.117752 115.964561 82.20295 C 115.988129 97.302063 115.613449 105.754272 114.845871 107.438927 C 114.211105 108.832092 112.634178 110.664902 111.341599 111.511833 C 110.049011 112.358765 107.755867 113.250313 106.24572 113.49305 Z M 83.919777 110.424141 C 81.750916 112.492821 81.745041 112.49295 82.222237 110.463524 C 82.485603 109.34346 83.234177 107.430954 83.885727 106.213524 C 84.537277 104.996086 85.557022 104 86.151817 104 C 86.769806 104 86.989609 104.931732 86.664711 106.174141 C 86.352013 107.369919 85.116791 109.282417 83.919777 110.424141 Z M 69.077126 73.460121 C 67.52417 74.263184 65.971512 74.638184 65.626785 74.293449 C 65.282051 73.948723 65 70.321663 65 66.23333 C 65 59.113068 65.143196 58.656799 68.400002 55.399994 C 71.269066 52.53093 72.509613 52 76.344238 52 C 79.70623 52 81.618423 52.627884 83.694221 54.413406 C 86.298683 56.653671 86.525444 57.447205 86.855125 65.474922 C 87.050461 70.231392 86.954063 74.379272 86.6409 74.692436 C 86.327736 75.005592 84.524918 74.527908 82.634628 73.630913 C 80.744339 72.73391 77.555901 72 75.54921 72 C 73.542519 72 70.630081 72.657051 69.077126 73.460121 Z"></path>
            </svg>
            <h1>I'M NOT A BEACON!</h1>
        </div>
    </header>
    
    <nav>
        <div class="nav-content">
            <span class="nav-text"> // NDR Testing Site</span>
        </div>
    </nav>
    
    <main>
        <div class="terminal">
            <div class="terminal-header">
                <div class="terminal-button close"></div>
                <div class="terminal-button minimize"></div>
                <div class="terminal-button maximize"></div>
                <div class="terminal-title"></div>
            </div>
            <div class="terminal-content">
                <div class="output">
                    <span class="prompt">➜</span> <span class="path">~</span> <span class="command">./welcome.sh</span>
                </div>
                <div class="output" style="margin-top: 20px;">
                    <span class="highlight">Hello, Security Researcher!</span>
                </div>
                <div class="output">
                    This is a <strong style="color: #4fc3f7;">Cobalt Strike Beacon Simulator</strong> for testing NDR solutions.
                </div>
                <div class="output">
                    The endpoint simulates C2 beacon traffic patterns to help you validate
                    your network detection capabilities in a controlled environment.
                </div>
                
                <div class="code-block">
                    <div class="comment"># Generate payload file with null bytes</div>
                    <code><span class="command">printf</span> <span class="highlight">'\x00\x00\x00\x00'</span> > payload.bin && <span class="command">head</span> -c 60 /dev/urandom >> payload.bin</code>
                </div>
                
                <div class="code-block">
                    <div class="comment"># Test with curl (realistic beacon POST request)</div>
                    <code><span class="command">curl</span> -X POST \<br>
     -H <span class="highlight">"Content-Type: application/octet-stream"</span> \<br>
     -H <span class="highlight">"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"</span> \<br>
     -H <span class="highlight">"Cookie: sessionid=$(head -c 96 /dev/urandom | base64)"</span> \<br>
     --data-binary <span class="highlight">"@payload.bin"</span> \<br>
     <span class="highlight">http://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']; ?>?id=12345</span></code>
                </div>
                
                <div class="output" style="margin-top: 20px;">
                    <span class="comment">Use the Python or PowerShell clients for automated testing.</span>
                </div>
                
                <div class="output" style="margin-top: 16px;">
                    <span class="prompt">➜</span> <span class="path">~</span> <span style="color: #fff;">▊</span>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="footer-content">
            <div class="footer-left">
                <div class="version">Version 1.0.0 | NDR Testing Tool</div>
                <div class="disclaimer">
                    <strong>⚠ DISCLAIMER:</strong> This tool is for authorized security testing only. 
                    Only use against systems you own or have explicit permission to test. 
                    Unauthorized use may violate laws and regulations.
                </div>
            </div>
            <div class="footer-right">
                <?php echo date('F j, Y'); ?>
            </div>
        </div>
    </footer>

    <canvas id="mesh-bg"></canvas>

    <script>
    (() => {
      const canvas = document.getElementById("mesh-bg");
      const ctx = canvas.getContext("2d", { alpha: false });

      let width = 0;
      let height = 0;
      let dpr = Math.min(window.devicePixelRatio || 1, 2);

      const LOOP_SECONDS = 16;
      const TAU = Math.PI * 2;

      const isMobile = () => window.innerWidth < 768;

      const CONFIG = {
        bg: "#ffffff",
        maxDistFg: 115,
        maxDistBg: 130,
        nodeColorFg: "rgba(0,0,0,0.9)",
        nodeColorBg: "rgba(0,0,0,0.28)",
        lineBaseAlphaFg: 0.18,
        lineBaseAlphaBg: 0.06,
        nodeSizeFg: [1.0, 1.8],
        nodeSizeBg: [0.8, 1.2],
      };

      let layers = [];

      function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = window.innerWidth;
        height = window.innerHeight;

        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        canvas.style.width = width + "px";
        canvas.style.height = height + "px";

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        buildScene();
      }

      function rand(min, max) {
        return Math.random() * (max - min) + min;
      }

      function lerp(a, b, t) {
        return a + (b - a) * t;
      }

      function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
      }

      function densityWeight(x, y) {
        const nx = x / width;
        const ny = y / height;

        const cluster1 =
          Math.exp(-(((nx - 0.72) ** 2) / 0.03 + ((ny - 0.62) ** 2) / 0.05));
        const cluster2 =
          Math.exp(-(((nx - 0.58) ** 2) / 0.05 + ((ny - 0.72) ** 2) / 0.08));
        const cluster3 =
          Math.exp(-(((nx - 0.88) ** 2) / 0.025 + ((ny - 0.58) ** 2) / 0.05));

        const band = Math.exp(-((ny - (0.72 - nx * 0.18)) ** 2) / 0.02);

        const sparsePenalty = nx < 0.35 && ny < 0.45 ? 0.22 : 1.0;

        return clamp((cluster1 + cluster2 + cluster3 + band * 0.8) * sparsePenalty, 0, 1.2);
      }

      function createParticle(layerType) {
        const isFg = layerType === "fg";

        let x, y, weight, tries = 0;
        do {
          x = rand(0, width);
          y = rand(0, height);
          weight = densityWeight(x, y);
          tries++;
        } while (Math.random() > Math.min(1, 0.15 + weight) && tries < 30);

        if (Math.random() < 0.7) {
          y = lerp(y, rand(height * 0.52, height * 0.92), 0.55);
        }

        return {
          baseX: x,
          baseY: y,
          x,
          y,
          r: isFg
            ? rand(CONFIG.nodeSizeFg[0], CONFIG.nodeSizeFg[1])
            : rand(CONFIG.nodeSizeBg[0], CONFIG.nodeSizeBg[1]),
          ampX: isFg ? rand(6, 18) : rand(8, 24),
          ampY: isFg ? rand(6, 16) : rand(8, 20),
          phase1: rand(0, TAU),
          phase2: rand(0, TAU),
          phase3: rand(0, TAU),
          bandInfluence: rand(0.3, 1.0),
        };
      }

      function createDetachedClusters(layerType, count) {
        const particles = [];
        for (let i = 0; i < count; i++) {
          const cx = rand(width * 0.62, width * 0.95);
          const cy = rand(height * 0.08, height * 0.38);
          const localCount = Math.floor(rand(6, 16));

          for (let j = 0; j < localCount; j++) {
            particles.push({
              baseX: cx + rand(-55, 55),
              baseY: cy + rand(-35, 35),
              x: cx,
              y: cy,
              r: layerType === "fg" ? rand(1.0, 1.7) : rand(0.8, 1.1),
              ampX: layerType === "fg" ? rand(5, 14) : rand(7, 18),
              ampY: layerType === "fg" ? rand(5, 12) : rand(6, 16),
              phase1: rand(0, TAU),
              phase2: rand(0, TAU),
              phase3: rand(0, TAU),
              bandInfluence: rand(0.2, 0.7),
            });
          }
        }
        return particles;
      }

      function buildScene() {
        const mobile = isMobile();

        const bgCount = mobile ? 70 : 130;
        const fgCount = mobile ? 130 : 280;

        const bgParticles = Array.from({ length: bgCount }, () => createParticle("bg"));
        const fgParticles = Array.from({ length: fgCount }, () => createParticle("fg"));

        bgParticles.push(...createDetachedClusters("bg", mobile ? 1 : 2));
        fgParticles.push(...createDetachedClusters("fg", mobile ? 2 : 4));

        layers = [
          {
            type: "bg",
            particles: bgParticles,
            speed: 0.55,
            maxDist: CONFIG.maxDistBg,
            lineAlpha: CONFIG.lineBaseAlphaBg,
            nodeColor: CONFIG.nodeColorBg,
          },
          {
            type: "fg",
            particles: fgParticles,
            speed: 1.0,
            maxDist: CONFIG.maxDistFg,
            lineAlpha: CONFIG.lineBaseAlphaFg,
            nodeColor: CONFIG.nodeColorFg,
          }
        ];
      }

      function updateParticle(p, tNorm, layerSpeed) {
        const angle = tNorm * TAU;

        const waveBand =
          Math.sin((p.baseX / width) * Math.PI * 1.4 + angle + p.phase3) * 10 * p.bandInfluence;

        p.x =
          p.baseX +
          Math.cos(angle * layerSpeed + p.phase1) * p.ampX +
          Math.sin(angle * 2 + p.phase2) * (p.ampX * 0.18);

        p.y =
          p.baseY +
          Math.sin(angle * layerSpeed + p.phase2) * p.ampY +
          Math.cos(angle * 2 + p.phase1) * (p.ampY * 0.15) +
          waveBand;
      }

      function drawConnections(layer) {
        const particles = layer.particles;
        const maxDist = layer.maxDist;
        const maxDistSq = maxDist * maxDist;

        for (let i = 0; i < particles.length; i++) {
          const a = particles[i];

          for (let j = i + 1; j < particles.length; j++) {
            const b = particles[j];

            const dx = a.x - b.x;
            const dy = a.y - b.y;
            const d2 = dx * dx + dy * dy;

            if (d2 > maxDistSq) continue;

            const d = Math.sqrt(d2);
            const alpha = layer.lineAlpha * (1 - d / maxDist);

            if (alpha <= 0.003) continue;

            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.strokeStyle = `rgba(0,0,0,${alpha.toFixed(4)})`;
            ctx.lineWidth = layer.type === "fg" ? 0.55 : 0.45;
            ctx.stroke();
          }
        }
      }

      function drawParticles(layer) {
        ctx.fillStyle = layer.nodeColor;
        for (const p of layer.particles) {
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, TAU);
          ctx.fill();
        }
      }

      function draw() {
        const now = performance.now() * 0.001;
        const tNorm = (now % LOOP_SECONDS) / LOOP_SECONDS;

        ctx.fillStyle = CONFIG.bg;
        ctx.fillRect(0, 0, width, height);

        for (const layer of layers) {
          for (const p of layer.particles) {
            updateParticle(p, tNorm, layer.speed);
          }
        }

        drawConnections(layers[0]);
        drawParticles(layers[0]);

        drawConnections(layers[1]);
        drawParticles(layers[1]);

        requestAnimationFrame(draw);
      }

      window.addEventListener("resize", resize, { passive: true });
      resize();
      requestAnimationFrame(draw);
    })();
    </script>
</body>
</html>
    <?php
    exit;
}

// Log request details for debugging (optional - comment out in production testing)
error_log("=== Cobalt Strike Beacon Check-in ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("URI: " . $_SERVER['REQUEST_URI']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Handle POST requests - beacon traffic
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit('Not Found');
}

// Read the POST body (beacon data)
$body = file_get_contents('php://input');
$body_length = strlen($body);

// Log beacon metadata
if (isset($_SERVER['HTTP_COOKIE'])) {
    error_log("Cookie: " . substr($_SERVER['HTTP_COOKIE'], 0, 50) . "...");
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    error_log("User-Agent: " . $_SERVER['HTTP_USER_AGENT']);
}
error_log("Payload length: " . $body_length . " bytes");

// Check for Cobalt Strike-like patterns
if ($body_length > 0) {
    $first_bytes = substr($body, 0, 4);
    $hex_preview = bin2hex($first_bytes);
    error_log("First 4 bytes (hex): " . $hex_preview);
}

// Simulate C2 server response
// Real Cobalt Strike servers may return:
// - Empty response (no tasks)
// - Encrypted task data
// - Binary response with commands

// For realistic NDR testing, return a small binary response
// that mimics an encrypted "no tasks" response
http_response_code(200);
header('Content-Type: application/octet-stream');
header('Connection: close');

// Generate a realistic C2 response (4-16 bytes of "encrypted" data)
// In real Cobalt Strike, this would be encrypted commands or acknowledgment
$response_size = rand(4, 16);
$response = random_bytes($response_size);

echo $response;

// Log the response
error_log("Response sent: " . $response_size . " bytes");
error_log("=====================================");
?>
