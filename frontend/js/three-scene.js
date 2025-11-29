// ===== THREE.JS SCENE MANAGER =====

class HotelThreeScene {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.animationId = null;
        
        // Scene objects
        this.particles = [];
        this.buildings = [];
        this.lights = [];
        this.clouds = [];
        
        // Animation properties
        this.clock = new THREE.Clock();
        this.mouseX = 0;
        this.mouseY = 0;
        this.targetX = 0;
        this.targetY = 0;
        
        // Performance settings
        this.isLowPerformance = this.detectLowPerformance();
        
        this.init();
    }
    
    detectLowPerformance() {
        // Simple performance detection
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (!gl) return true;
        
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
            const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            if (renderer.includes('Intel') || renderer.includes('Software')) {
                return true;
            }
        }
        
        return window.innerWidth < 768; // Mobile devices
    }
    
    init() {
        if (!this.container) {
            console.warn('Three.js container not found');
            return;
        }
        
        this.createScene();
        this.createCamera();
        this.createRenderer();
        this.createLights();
        this.createParticles();
        this.createBuildings();
        this.createClouds();
        this.setupEventListeners();
        this.animate();
        
        console.log('Three.js scene initialized');
    }
    
    createScene() {
        this.scene = new THREE.Scene();
        this.scene.fog = new THREE.Fog(0x1a1a1a, 100, 1000);
    }
    
    createCamera() {
        const aspect = this.container.clientWidth / this.container.clientHeight;
        this.camera = new THREE.PerspectiveCamera(75, aspect, 0.1, 2000);
        this.camera.position.set(0, 50, 100);
        this.camera.lookAt(0, 0, 0);
    }
    
    createRenderer() {
        this.renderer = new THREE.WebGLRenderer({
            antialias: !this.isLowPerformance,
            alpha: true,
            powerPreference: this.isLowPerformance ? 'low-power' : 'high-performance'
        });
        
        this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
        this.renderer.setPixelRatio(this.isLowPerformance ? 1 : Math.min(window.devicePixelRatio, 2));
        this.renderer.setClearColor(0x000000, 0);
        this.renderer.shadowMap.enabled = !this.isLowPerformance;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        
        this.container.appendChild(this.renderer.domElement);
    }
    
    createLights() {
        // Ambient light
        const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
        this.scene.add(ambientLight);
        
        // Main directional light
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(100, 100, 50);
        directionalLight.castShadow = !this.isLowPerformance;
        directionalLight.shadow.mapSize.width = this.isLowPerformance ? 1024 : 2048;
        directionalLight.shadow.mapSize.height = this.isLowPerformance ? 1024 : 2048;
        this.scene.add(directionalLight);
        this.lights.push(directionalLight);
        
        // Golden accent lights
        const goldLight1 = new THREE.PointLight(0xd4af37, 1, 200);
        goldLight1.position.set(-50, 30, 50);
        this.scene.add(goldLight1);
        this.lights.push(goldLight1);
        
        const goldLight2 = new THREE.PointLight(0xd4af37, 1, 200);
        goldLight2.position.set(50, 30, 50);
        this.scene.add(goldLight2);
        this.lights.push(goldLight2);
    }
    
    createParticles() {
        const particleCount = this.isLowPerformance ? 500 : 1500;
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(particleCount * 3);
        const colors = new Float32Array(particleCount * 3);
        const sizes = new Float32Array(particleCount);
        
        const color = new THREE.Color();
        
        for (let i = 0; i < particleCount; i++) {
            const i3 = i * 3;
            
            // Position
            positions[i3] = (Math.random() - 0.5) * 400;
            positions[i3 + 1] = Math.random() * 200 - 50;
            positions[i3 + 2] = (Math.random() - 0.5) * 400;
            
            // Color (golden particles)
            color.setHSL(0.15 + Math.random() * 0.1, 0.7, 0.5 + Math.random() * 0.3);
            colors[i3] = color.r;
            colors[i3 + 1] = color.g;
            colors[i3 + 2] = color.b;
            
            // Size
            sizes[i] = Math.random() * 3 + 1;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));
        
        const material = new THREE.ShaderMaterial({
            uniforms: {
                time: { value: 0 }
            },
            vertexShader: `
                attribute float size;
                attribute vec3 color;
                varying vec3 vColor;
                uniform float time;
                
                void main() {
                    vColor = color;
                    vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
                    
                    // Floating animation
                    mvPosition.y += sin(time + position.x * 0.01) * 5.0;
                    mvPosition.x += cos(time + position.z * 0.01) * 2.0;
                    
                    gl_PointSize = size * (300.0 / -mvPosition.z);
                    gl_Position = projectionMatrix * mvPosition;
                }
            `,
            fragmentShader: `
                varying vec3 vColor;
                
                void main() {
                    float distance = length(gl_PointCoord - vec2(0.5));
                    if (distance > 0.5) discard;
                    
                    float alpha = 1.0 - distance * 2.0;
                    gl_FragColor = vec4(vColor, alpha * 0.8);
                }
            `,
            transparent: true,
            vertexColors: true,
            blending: THREE.AdditiveBlending
        });
        
        const particles = new THREE.Points(geometry, material);
        this.scene.add(particles);
        this.particles.push({ mesh: particles, material: material });
    }
    
    createBuildings() {
        const buildingCount = this.isLowPerformance ? 5 : 12;
        
        for (let i = 0; i < buildingCount; i++) {
            const width = 10 + Math.random() * 20;
            const height = 30 + Math.random() * 80;
            const depth = 10 + Math.random() * 20;
            
            const geometry = new THREE.BoxGeometry(width, height, depth);
            const material = new THREE.MeshLambertMaterial({
                color: new THREE.Color().setHSL(0.6, 0.2, 0.1 + Math.random() * 0.3),
                transparent: true,
                opacity: 0.8
            });
            
            const building = new THREE.Mesh(geometry, material);
            building.position.set(
                (Math.random() - 0.5) * 300,
                height / 2 - 20,
                -100 - Math.random() * 200
            );
            
            building.castShadow = !this.isLowPerformance;
            building.receiveShadow = !this.isLowPerformance;
            
            this.scene.add(building);
            this.buildings.push(building);
            
            // Add windows
            if (!this.isLowPerformance) {
                this.addWindows(building, width, height, depth);
            }
        }
    }
    
    addWindows(building, width, height, depth) {
        const windowsPerFloor = Math.floor(width / 3);
        const floors = Math.floor(height / 8);
        
        for (let floor = 0; floor < floors; floor++) {
            for (let window = 0; window < windowsPerFloor; window++) {
                if (Math.random() > 0.3) { // 70% chance of lit window
                    const windowGeometry = new THREE.PlaneGeometry(1.5, 2);
                    const windowMaterial = new THREE.MeshBasicMaterial({
                        color: 0xffff88,
                        transparent: true,
                        opacity: 0.8
                    });
                    
                    const windowMesh = new THREE.Mesh(windowGeometry, windowMaterial);
                    windowMesh.position.set(
                        (window - windowsPerFloor / 2) * 3,
                        (floor - floors / 2) * 8,
                        depth / 2 + 0.1
                    );
                    
                    building.add(windowMesh);
                }
            }
        }
    }
    
    createClouds() {
        const cloudCount = this.isLowPerformance ? 3 : 8;
        
        for (let i = 0; i < cloudCount; i++) {
            const cloudGeometry = new THREE.SphereGeometry(20, 8, 6);
            const cloudMaterial = new THREE.MeshLambertMaterial({
                color: 0xffffff,
                transparent: true,
                opacity: 0.3
            });
            
            const cloud = new THREE.Mesh(cloudGeometry, cloudMaterial);
            cloud.position.set(
                (Math.random() - 0.5) * 400,
                80 + Math.random() * 40,
                -200 - Math.random() * 100
            );
            
            cloud.scale.set(
                1 + Math.random(),
                0.5 + Math.random() * 0.5,
                1 + Math.random()
            );
            
            this.scene.add(cloud);
            this.clouds.push(cloud);
        }
    }
    
    setupEventListeners() {
        // Mouse movement
        document.addEventListener('mousemove', (event) => {
            this.mouseX = (event.clientX - window.innerWidth / 2) / 100;
            this.mouseY = (event.clientY - window.innerHeight / 2) / 100;
        });
        
        // Window resize
        window.addEventListener('resize', () => {
            this.onWindowResize();
        });
        
        // Scroll parallax
        window.addEventListener('scroll', () => {
            this.onScroll();
        });
        
        // Visibility change (pause when tab is not active)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pause();
            } else {
                this.resume();
            }
        });
    }
    
    onWindowResize() {
        if (!this.container) return;
        
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        
        this.renderer.setSize(width, height);
    }
    
    onScroll() {
        const scrollY = window.pageYOffset;
        const maxScroll = document.body.scrollHeight - window.innerHeight;
        const scrollProgress = Math.min(scrollY / maxScroll, 1);
        
        // Move camera based on scroll
        this.camera.position.y = 50 + scrollProgress * 30;
        this.camera.position.z = 100 - scrollProgress * 50;
    }
    
    animate() {
        this.animationId = requestAnimationFrame(() => this.animate());
        
        const elapsedTime = this.clock.getElapsedTime();
        
        // Update particles
        this.particles.forEach(particle => {
            particle.material.uniforms.time.value = elapsedTime;
        });
        
        // Smooth camera movement based on mouse
        this.targetX = this.mouseX * 0.5;
        this.targetY = this.mouseY * 0.5;
        
        this.camera.position.x += (this.targetX - this.camera.position.x) * 0.02;
        this.camera.position.y += (this.targetY - this.camera.position.y) * 0.02;
        
        // Animate clouds
        this.clouds.forEach((cloud, index) => {
            cloud.position.x += Math.sin(elapsedTime * 0.1 + index) * 0.1;
            cloud.rotation.y += 0.001;
        });
        
        // Animate buildings (subtle movement)
        this.buildings.forEach((building, index) => {
            building.rotation.y += Math.sin(elapsedTime * 0.05 + index) * 0.0005;
        });
        
        // Animate lights
        this.lights.forEach((light, index) => {
            if (light.type === 'PointLight') {
                light.intensity = 0.8 + Math.sin(elapsedTime * 2 + index) * 0.2;
            }
        });
        
        this.renderer.render(this.scene, this.camera);
    }
    
    pause() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }
    
    resume() {
        if (!this.animationId) {
            this.animate();
        }
    }
    
    destroy() {
        this.pause();
        
        // Clean up geometries and materials
        this.scene.traverse((object) => {
            if (object.geometry) {
                object.geometry.dispose();
            }
            if (object.material) {
                if (Array.isArray(object.material)) {
                    object.material.forEach(material => material.dispose());
                } else {
                    object.material.dispose();
                }
            }
        });
        
        // Remove renderer
        if (this.renderer && this.container) {
            this.container.removeChild(this.renderer.domElement);
            this.renderer.dispose();
        }
        
        // Remove event listeners
        window.removeEventListener('resize', this.onWindowResize);
        window.removeEventListener('scroll', this.onScroll);
        document.removeEventListener('mousemove', this.onMouseMove);
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
    }
}

// ===== ROOM PREVIEW 3D SCENE =====
class RoomPreviewScene {
    constructor(containerId, roomData) {
        this.container = document.getElementById(containerId);
        this.roomData = roomData;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.animationId = null;
        
        this.init();
    }
    
    init() {
        if (!this.container) return;
        
        this.createScene();
        this.createCamera();
        this.createRenderer();
        this.createRoom();
        this.createControls();
        this.animate();
    }
    
    createScene() {
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0xf0f0f0);
    }
    
    createCamera() {
        const aspect = this.container.clientWidth / this.container.clientHeight;
        this.camera = new THREE.PerspectiveCamera(75, aspect, 0.1, 1000);
        this.camera.position.set(0, 5, 10);
    }
    
    createRenderer() {
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        
        this.container.appendChild(this.renderer.domElement);
    }
    
    createRoom() {
        // Room walls
        const roomGeometry = new THREE.BoxGeometry(20, 10, 15);
        const roomMaterial = new THREE.MeshLambertMaterial({
            color: 0xffffff,
            side: THREE.BackSide
        });
        const room = new THREE.Mesh(roomGeometry, roomMaterial);
        this.scene.add(room);
        
        // Floor
        const floorGeometry = new THREE.PlaneGeometry(20, 15);
        const floorMaterial = new THREE.MeshLambertMaterial({ color: 0x8B4513 });
        const floor = new THREE.Mesh(floorGeometry, floorMaterial);
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -5;
        floor.receiveShadow = true;
        this.scene.add(floor);
        
        // Bed
        this.createBed();
        
        // Furniture
        this.createFurniture();
        
        // Lighting
        this.createRoomLighting();
    }
    
    createBed() {
        // Bed frame
        const bedFrameGeometry = new THREE.BoxGeometry(6, 1, 8);
        const bedFrameMaterial = new THREE.MeshLambertMaterial({ color: 0x8B4513 });
        const bedFrame = new THREE.Mesh(bedFrameGeometry, bedFrameMaterial);
        bedFrame.position.set(0, -4, -3);
        bedFrame.castShadow = true;
        this.scene.add(bedFrame);
        
        // Mattress
        const mattressGeometry = new THREE.BoxGeometry(5.8, 0.8, 7.8);
        const mattressMaterial = new THREE.MeshLambertMaterial({ color: 0xffffff });
        const mattress = new THREE.Mesh(mattressGeometry, mattressMaterial);
        mattress.position.set(0, -3.1, -3);
        mattress.castShadow = true;
        this.scene.add(mattress);
        
        // Pillows
        for (let i = 0; i < 2; i++) {
            const pillowGeometry = new THREE.BoxGeometry(1.5, 0.3, 1);
            const pillowMaterial = new THREE.MeshLambertMaterial({ color: 0xd4af37 });
            const pillow = new THREE.Mesh(pillowGeometry, pillowMaterial);
            pillow.position.set((i - 0.5) * 2, -2.5, -6);
            pillow.castShadow = true;
            this.scene.add(pillow);
        }
    }
    
    createFurniture() {
        // Nightstand
        const nightstandGeometry = new THREE.BoxGeometry(1.5, 2, 1.5);
        const nightstandMaterial = new THREE.MeshLambertMaterial({ color: 0x8B4513 });
        const nightstand = new THREE.Mesh(nightstandGeometry, nightstandMaterial);
        nightstand.position.set(4, -4, -3);
        nightstand.castShadow = true;
        this.scene.add(nightstand);
        
        // Chair
        const chairGeometry = new THREE.BoxGeometry(1, 2, 1);
        const chairMaterial = new THREE.MeshLambertMaterial({ color: 0xd4af37 });
        const chair = new THREE.Mesh(chairGeometry, chairMaterial);
        chair.position.set(-6, -4, 2);
        chair.castShadow = true;
        this.scene.add(chair);
        
        // Table
        const tableGeometry = new THREE.BoxGeometry(2, 0.2, 2);
        const tableMaterial = new THREE.MeshLambertMaterial({ color: 0x8B4513 });
        const table = new THREE.Mesh(tableGeometry, tableMaterial);
        table.position.set(-6, -2, 2);
        table.castShadow = true;
        this.scene.add(table);
    }
    
    createRoomLighting() {
        // Ambient light
        const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
        this.scene.add(ambientLight);
        
        // Main light
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(10, 10, 5);
        directionalLight.castShadow = true;
        directionalLight.shadow.mapSize.width = 2048;
        directionalLight.shadow.mapSize.height = 2048;
        this.scene.add(directionalLight);
        
        // Warm accent light
        const pointLight = new THREE.PointLight(0xffaa00, 0.5, 50);
        pointLight.position.set(4, -2, -3);
        this.scene.add(pointLight);
    }
    
    createControls() {
        // Simple orbit controls simulation
        let isMouseDown = false;
        let mouseX = 0;
        let mouseY = 0;
        
        this.container.addEventListener('mousedown', (event) => {
            isMouseDown = true;
            mouseX = event.clientX;
            mouseY = event.clientY;
        });
        
        this.container.addEventListener('mouseup', () => {
            isMouseDown = false;
        });
        
        this.container.addEventListener('mousemove', (event) => {
            if (!isMouseDown) return;
            
            const deltaX = event.clientX - mouseX;
            const deltaY = event.clientY - mouseY;
            
            // Rotate camera around the room
            const spherical = new THREE.Spherical();
            spherical.setFromVector3(this.camera.position);
            spherical.theta -= deltaX * 0.01;
            spherical.phi += deltaY * 0.01;
            spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi));
            
            this.camera.position.setFromSpherical(spherical);
            this.camera.lookAt(0, 0, 0);
            
            mouseX = event.clientX;
            mouseY = event.clientY;
        });
        
        // Zoom with mouse wheel
        this.container.addEventListener('wheel', (event) => {
            const scale = event.deltaY > 0 ? 1.1 : 0.9;
            this.camera.position.multiplyScalar(scale);
            
            // Limit zoom
            const distance = this.camera.position.length();
            if (distance < 5) {
                this.camera.position.normalize().multiplyScalar(5);
            } else if (distance > 30) {
                this.camera.position.normalize().multiplyScalar(30);
            }
        });
    }
    
    animate() {
        this.animationId = requestAnimationFrame(() => this.animate());
        this.renderer.render(this.scene, this.camera);
    }
    
    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        
        if (this.renderer && this.container) {
            this.container.removeChild(this.renderer.domElement);
            this.renderer.dispose();
        }
    }
}

// ===== INITIALIZE SCENES =====
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main hero scene
    if (document.getElementById('three-container')) {
        window.hotelScene = new HotelThreeScene('three-container');
    }
    
    // Initialize room preview scenes
    document.querySelectorAll('.room-preview-container').forEach((container, index) => {
        const roomData = { id: index }; // You can pass actual room data here
        new RoomPreviewScene(container.id, roomData);
    });
});

// Export classes for use in other scripts
window.HotelThreeScene = HotelThreeScene;
window.RoomPreviewScene = RoomPreviewScene;

console.log('Three.js scenes loaded successfully');

