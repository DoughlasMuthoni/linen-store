<?php
// /linen-closet/about.php

require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/App.php';
require_once 'includes/header.php';

$app = new App();

// Set page title
$pageTitle = "About Us | Our Story & Mission";
?>

<!-- Hero Section -->
<section class="about-hero py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <span class="badge bg-dark text-light mb-3 px-3 py-2 rounded-pill">Our Story</span>
                <h1 class="display-3 fw-bold mb-4">Crafting Timeless Linen Elegance</h1>
                <p class="lead mb-5">We believe in creating sustainable, comfortable, and stylish linen clothing that stands the test of time while embracing Kenya's rich cultural heritage.</p>
                <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg px-5 py-3">
                    <i class="fas fa-shopping-bag me-2"></i>Shop Our Collection
                </a>
            </div>
            <div class="col-lg-6">
                <div class="position-relative">
                    <img src="<?php echo SITE_URL; ?>assets/images/hero-banner.jpg" 
                         alt="Linen Craftsmanship" 
                         class="img-fluid rounded-4 shadow-lg">
                    <div class="position-absolute bottom-0 end-0 bg-white p-4 rounded-3 shadow-sm" style="transform: translate(-20px, 20px); max-width: 300px;">
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">Sustainable Since 2018</h5>
                                <small class="text-muted">Ethical production</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Story -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="row align-items-center">
            <div class="col-lg-6 order-lg-2 mb-5 mb-lg-0">
                <div class="position-relative">
                    <img src="<?php echo SITE_URL; ?>assets/images/insta-3.jpg" 
                         alt="Our Story" 
                         class="img-fluid rounded-4 shadow-lg">
                    <div class="position-absolute top-0 start-0 bg-dark text-light p-4 rounded-3" style="transform: translate(-20px, 20px); max-width: 250px;">
                        <h3 class="h4 mb-2">5+ Years</h3>
                        <p class="mb-0 small">Of crafting premium linen</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 order-lg-1">
                <span class="badge bg-dark text-light mb-3 px-3 py-2">Our Journey</span>
                <h2 class="display-5 fw-bold mb-4">From Humble Beginnings to Kenyan Excellence</h2>
                <div class="mb-4">
                    <p>Founded in 2018 in Nairobi, our journey began with a simple vision: to create high-quality linen clothing that combines comfort, style, and sustainability. What started as a small workshop with three artisans has grown into a beloved Kenyan brand known for exceptional craftsmanship.</p>
                    <p>We partner with local farmers who grow premium flax using sustainable practices, ensuring every piece tells a story of ethical production and community support.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-hands-helping text-dark"></i>
                            </div>
                            <div>
                                <h5 class="h6 fw-bold mb-1">Community First</h5>
                                <p class="small mb-0">Supporting local artisans & farmers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-seedling text-dark"></i>
                            </div>
                            <div>
                                <h5 class="h6 fw-bold mb-1">Sustainable Growth</h5>
                                <p class="small mb-0">Eco-friendly practices from farm to fabric</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Values -->
<section class="py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="text-center mb-5">
            <span class="badge bg-dark text-light mb-3 px-3 py-2">Our Values</span>
            <h2 class="display-5 fw-bold mb-3">What We Stand For</h2>
            <p class="lead text-muted">Guiding principles that shape everything we do</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="value-card bg-white p-4 rounded-4 shadow-sm h-100 text-center">
                    <div class="value-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                        <i class="fas fa-leaf fa-2x"></i>
                    </div>
                    <h3 class="h4 fw-bold mb-3">Sustainability</h3>
                    <p class="mb-0">We prioritize eco-friendly materials and production methods, minimizing our environmental footprint while creating products that last.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="value-card bg-white p-4 rounded-4 shadow-sm h-100 text-center">
                    <div class="value-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                        <i class="fas fa-gem fa-2x"></i>
                    </div>
                    <h3 class="h4 fw-bold mb-3">Quality Craftsmanship</h3>
                    <p class="mb-0">Every piece is meticulously crafted by skilled artisans who take pride in creating timeless garments of exceptional quality.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="value-card bg-white p-4 rounded-4 shadow-sm h-100 text-center">
                    <div class="value-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                        <i class="fas fa-heart fa-2x"></i>
                    </div>
                    <h3 class="h4 fw-bold mb-3">Ethical Practices</h3>
                    <p class="mb-0">Fair wages, safe working conditions, and respect for our artisans and farmers are non-negotiable parts of our business.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Process -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="text-center mb-5">
            <span class="badge bg-dark text-light mb-3 px-3 py-2">Our Process</span>
            <h2 class="display-5 fw-bold mb-3">From Flax to Fashion</h2>
            <p class="lead text-muted">How we create our premium linen collection</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="process-step text-center">
                    <div class="step-number bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                        <span class="fs-4 fw-bold">1</span>
                    </div>
                    <h4 class="h5 fw-bold mb-2">Sustainable Farming</h4>
                    <p class="small mb-0">Partnering with local farmers who grow flax using organic and sustainable methods.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="process-step text-center">
                    <div class="step-number bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                        <span class="fs-4 fw-bold">2</span>
                    </div>
                    <h4 class="h5 fw-bold mb-2">Traditional Weaving</h4>
                    <p class="small mb-0">Skilled artisans transform flax into premium linen fabric using time-honored techniques.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="process-step text-center">
                    <div class="step-number bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                        <span class="fs-4 fw-bold">3</span>
                    </div>
                    <h4 class="h5 fw-bold mb-2">Thoughtful Design</h4>
                    <p class="small mb-0">Creating timeless pieces that blend comfort, style, and functionality for everyday wear.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="process-step text-center">
                    <div class="step-number bg-dark text-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px;">
                        <span class="fs-4 fw-bold">4</span>
                    </div>
                    <h4 class="h5 fw-bold mb-2">Quality Assurance</h4>
                    <p class="small mb-0">Every garment undergoes rigorous quality checks before reaching our customers.</p>
                </div>
</div>
        </div>
    </div>
</section>

<!-- Meet the Team -->
<section class="py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="text-center mb-5">
            <span class="badge bg-dark text-light mb-3 px-3 py-2">Our Team</span>
            <h2 class="display-5 fw-bold mb-3">Meet Our Artisans</h2>
            <p class="lead text-muted">The talented individuals behind our beautiful creations</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="team-card bg-white rounded-4 overflow-hidden shadow-sm">
                    <div class="team-image" style="height: 300px; overflow: hidden;">
                        <img src="<?php echo SITE_URL; ?>assets/images/insta-2.jpg" 
                             alt="Sarah Mwangi" 
                             class="img-fluid w-100 h-100 object-fit-cover">
                    </div>
                    <div class="team-content p-4">
                        <h4 class="h5 fw-bold mb-1">Sarah Mwangi</h4>
                        <p class="text-muted small mb-2">Head Designer & Founder</p>
                        <p class="small mb-0">With 15 years of fashion design experience, Sarah combines traditional Kenyan patterns with modern linen silhouettes.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="team-card bg-white rounded-4 overflow-hidden shadow-sm">
                    <div class="team-image" style="height: 300px; overflow: hidden;">
                        <img src="<?php echo SITE_URL; ?>assets/images/insta-4.jpg" 
                             alt="James Ochieng" 
                             class="img-fluid w-100 h-100 object-fit-cover">
                    </div>
                    <div class="team-content p-4">
                        <h4 class="h5 fw-bold mb-1">James Ochieng</h4>
                        <p class="text-muted small mb-2">Master Weaver</p>
                        <p class="small mb-0">A third-generation weaver, James brings decades of traditional weaving knowledge to create our signature linen fabric.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="team-card bg-white rounded-4 overflow-hidden shadow-sm">
                    <div class="team-image" style="height: 300px; overflow: hidden;">
                        <img src="<?php echo SITE_URL; ?>assets/images/insta-5.jpg" 
                             alt="Grace Wanjiku" 
                             class="img-fluid w-100 h-100 object-fit-cover">
                    </div>
                    <div class="team-content p-4">
                        <h4 class="h5 fw-bold mb-1">Grace Wanjiku</h4>
                        <p class="text-muted small mb-2">Sustainability Lead</p>
                        <p class="small mb-0">Grace ensures all our practices meet the highest environmental standards while supporting our farming communities.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="text-center mb-5">
            <span class="badge bg-dark text-light mb-3 px-3 py-2">Testimonials</span>
            <h2 class="display-5 fw-bold mb-3">What Our Customers Say</h2>
            <p class="lead text-muted">Real stories from our community</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                    <div class="rating-stars text-warning mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="mb-4">"As someone who values both style and sustainability, finding this brand was a game-changer. The quality is exceptional and knowing I'm supporting local artisans makes each piece even more special."</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Amina Hassan</h6>
                            <small class="text-muted">Nairobi, 2 years customer</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                    <div class="rating-stars text-warning mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="mb-4">"The linen quality is unlike anything I've found elsewhere. Perfect for Kenya's climate - breathable, durable, and gets better with every wash. I've recommended to all my friends!"</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">David Kimani</h6>
                            <small class="text-muted">Mombasa, 1 year customer</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                    <div class="rating-stars text-warning mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="mb-4">"I appreciate the transparency about their production process. Knowing my clothes support local farmers and artisans while being environmentally responsible is important to me."</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Sophia Wangari</h6>
                            <small class="text-muted">Kisumu, 3 years customer</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-5 py-lg-7 bg-dark text-light">
    <div class="container px-0">
        <div class="row g-4 text-center">
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold mb-2">5+</h3>
                    <p class="mb-0">Years of Excellence</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold mb-2">5000+</h3>
                    <p class="mb-0">Happy Customers</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold mb-2">50+</h3>
                    <p class="mb-0">Local Artisans</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 class="display-4 fw-bold mb-2">100%</h3>
                    <p class="mb-0">Sustainable Materials</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="bg-light p-5 rounded-4 shadow-sm">
                    <i class="fas fa-hands-helping display-1 text-dark mb-4"></i>
                    <h2 class="display-5 fw-bold mb-3">Join Our Journey</h2>
                    <p class="lead mb-4">Be part of our mission to create sustainable, beautiful linen clothing while supporting Kenyan communities.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg px-5 py-3">
                            <i class="fas fa-shopping-bag me-2"></i>Shop Collection
                        </a>
                        <a href="<?php echo SITE_URL; ?>contact" class="btn btn-outline-dark btn-lg px-5 py-3">
                            <i class="fas fa-envelope me-2"></i>Get In Touch
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* About Page Specific Styles */
.about-hero {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.value-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.value-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important;
}

.value-icon {
    transition: transform 0.3s ease, background-color 0.3s ease;
}

.value-card:hover .value-icon {
    transform: scale(1.1);
    background-color: #495057 !important;
}

.team-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

.team-image {
    transition: transform 0.5s ease;
}

.team-card:hover .team-image img {
    transform: scale(1.05);
}

.testimonial-card {
    transition: transform 0.3s ease;
}

.testimonial-card:hover {
    transform: translateY(-5px);
}

.stat-item {
    padding: 20px;
}

.process-step {
    padding: 20px;
    position: relative;
}

.process-step:not(:last-child):after {
    content: '';
    position: absolute;
    top: 30px;
    right: -10px;
    width: 20px;
    height: 2px;
    background: #dee2e6;
    display: none;
}

@media (min-width: 768px) {
    .process-step:not(:last-child):after {
        display: block;
    }
}

.rating-stars {
    font-size: 1rem;
}

.customer-avatar {
    font-size: 1rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .display-3 {
        font-size: 2rem;
    }
    
    .display-4 {
        font-size: 1.75rem;
    }
    
    .display-5 {
        font-size: 1.5rem;
    }
    
    .stat-item h3 {
        font-size: 2.5rem;
    }
}

@media (max-width: 576px) {
    .stat-item {
        padding: 15px;
    }
    
    .stat-item h3 {
        font-size: 2rem;
    }
}
</style>

<script>
// Add any interactive elements if needed
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects for team cards
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            const img = this.querySelector('.team-image img');
            if (img) {
                img.style.transition = 'transform 0.5s ease';
                img.style.transform = 'scale(1.05)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const img = this.querySelector('.team-image img');
            if (img) {
                img.style.transform = 'scale(1)';
            }
        });
    });
    
    // Add animation for value cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe value cards
    document.querySelectorAll('.value-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(card);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>