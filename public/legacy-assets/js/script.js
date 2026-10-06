document.addEventListener("DOMContentLoaded", () => {
    console.log("Welcome, noble visitor of 3DExpert!");
});
document.addEventListener("DOMContentLoaded", function () {
    const hamburger = document.getElementById("hamburger");
    const navLinks = document.getElementById("nav-links");
  
    hamburger.addEventListener("click", function () {
      navLinks.classList.toggle("show");
    });
  });

// Simple script to handle location slider functionality
document.addEventListener('DOMContentLoaded', function() {
    // Variables
    const nextBtn = document.querySelector('.location-section .next-btn');
    const indicators = document.querySelectorAll('.location-section .slider-indicator');
    const locationWrapper = document.querySelector('.location-section .location-wrapper');
    
    // Store location data (you can expand this with actual locations)
    const locations = [
        {
            title: "HEAD OFFICE PT LUXORA AURA SEMESTA (3D EXPERT)",
            address: "Jl. Bendul Merisi Selatan VII No.57, Bendul Merisi, Kec. Wonocolo, Surabaya, Jawa Timur 60239",
            mapLink: "https://maps.app.goo.gl/k8YAm2kHWvfxJhDQ9",
            embedSrc: "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.4326862362433!2d112.74011237489305!3d-7.310935972292565!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd7fb0c2c182d01%3A0x5bc143eafb28d974!2sJl.%20Bendul%20Merisi%20Sel.%20VII%20No.57%2C%20Bendul%20Merisi%2C%20Kec.%20Wonocolo%2C%20Surabaya%2C%20Jawa%20Timur%2060239!5e0!3m2!1sen!2sid!4v1687168769999!5m2!1sen!2sid&markers=color:red%7Clabel:3DEXPERT%7C-7.310935972292565,112.74011237489305"
        },
        {
            title: "BRANCH OFFICE JAKARTA",
            address: "Jl. Example Street No. 123, Jakarta Selatan, DKI Jakarta 12345",
            mapLink: "https://maps.app.goo.gl/example1",
            embedSrc: "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.4220698333346!2d106.82968231535144!3d-6.208976962537601!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f3f21ddb960f%3A0x1d0c879fcd4a3685!2sJakarta!5e0!3m2!1sen!2sid!4v1621234567890!5m2!1sen!2sid&markers=color:red%7Clabel:3DJAKARTA%7C-6.208976962537601,106.82968231535144"
        },
        {
            title: "BRANCH OFFICE BALI",
            address: "Jl. Sample Road No. 456, Denpasar, Bali 80234",
            mapLink: "https://maps.app.goo.gl/example2",
            embedSrc: "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126312.4077999943!2d115.14745399648058!3d-8.670458028159518!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd2409b0e5e80db%3A0xe27334e8575b175a!2sDenpasar%2C%20Bali!5e0!3m2!1sen!2sid!4v1621234567891!5m2!1sen!2sid&markers=color:red%7Clabel:3DBALI%7C-8.670458028159518,115.14745399648058"
        }
    ];
    
    let currentIndex = 0;
    
    // Function to update the location content
    function updateLocation(index) {
        // Update the active indicator
        indicators.forEach((indicator, i) => {
            if (i === index) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
        
        // Get location data
        const location = locations[index];
        
        // Update content with animation
        locationWrapper.style.opacity = '0';
        
        setTimeout(() => {
            // Update the text content
            document.querySelector('.office-label').innerHTML = location.title.replace(' (3D EXPERT)', '<br>(3D EXPERT)');
            document.querySelector('.location-address').textContent = location.address;
            document.querySelector('.location-map-link').href = location.mapLink;
            
            // Update the map iframe
            const iframe = document.querySelector('.location-map iframe');
            if (iframe) {
                iframe.src = location.embedSrc;
            }
            
            // Fade back in
            locationWrapper.style.opacity = '1';
        }, 300);
    }
    
    // Event listeners
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentIndex = (currentIndex + 1) % locations.length;
            updateLocation(currentIndex);
        });
    }
    
    // Allow clicking on indicators to change location
    indicators.forEach((indicator, i) => {
        indicator.addEventListener('click', function() {
            currentIndex = i;
            updateLocation(currentIndex);
        });
    });
    
    // Add a fix for iframe loading issues
    const mapIframe = document.querySelector('.location-map iframe');
    const fallbackImg = document.querySelector('.fallback-map');
    
    if (mapIframe && fallbackImg) {
        // Show fallback image if iframe fails to load
        mapIframe.addEventListener('error', function() {
            mapIframe.style.display = 'none';
            fallbackImg.style.zIndex = '1';
        });
        
        // Hide fallback image once iframe loads successfully
        mapIframe.addEventListener('load', function() {
            mapIframe.style.display = 'block';
            fallbackImg.style.zIndex = '-1';
        });
    }
});
  
  // Portfolio Filter Functionality
document.addEventListener('DOMContentLoaded', function() {
  // Cek apakah halaman memiliki elemen filter portofolio
  if (document.querySelector('.category-filters')) {
      const filterButtons = document.querySelectorAll('.filter-btn');
      const portfolioItems = document.querySelectorAll('.portfolio-item');
      
      // Tambahkan event listener untuk tombol filter
      filterButtons.forEach(button => {
          button.addEventListener('click', function() {
              // Hapus class active dari semua tombol
              filterButtons.forEach(btn => btn.classList.remove('active'));
              
              // Tambahkan class active ke tombol yang diklik
              this.classList.add('active');
              
              // Ambil category yang dipilih
              const filterValue = this.getAttribute('data-filter');
              
              // Filter item portfolio
              portfolioItems.forEach(item => {
                  if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                      item.style.display = 'block';
                      // Tambahkan animasi fade in
                      setTimeout(() => {
                          item.style.opacity = '1';
                      }, 100);
                  } else {
                      item.style.opacity = '0';
                      setTimeout(() => {
                          item.style.display = 'none';
                      }, 300);
                  }
              });
          });
      });
  }
});