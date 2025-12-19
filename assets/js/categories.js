// /linen-closet/assets/js/categories.js

// Global variables (will be set from PHP)
if (typeof window.SITE_URL === 'undefined') {
    window.SITE_URL = 'http://localhost/linen-closet/';
}

// Debug function - check all form elements
function checkEditFormElements() {
    console.log("Checking edit form elements...");
    const requiredIds = [
        "edit_category_id",
        "edit_name",
        "edit_slug",
        "edit_description",
        "edit_image_url",
        "edit_parent_id",
        "edit_is_active"
    ];
    
    requiredIds.forEach(id => {
        const element = document.getElementById(id);
        console.log(id + ":", element ? "✓ FOUND" : "✗ NOT FOUND", element);
    });
    
    const modal = document.getElementById("editCategoryModal");
    console.log("Edit modal:", modal ? "✓ FOUND" : "✗ NOT FOUND");
}

// Auto-generate slug from name
function initializeSlugGeneration() {
    const nameInput = document.getElementById("name");
    const slugInput = document.getElementById("slug");
    const editNameInput = document.getElementById("edit_name");
    const editSlugInput = document.getElementById("edit_slug");
    
    if (nameInput && slugInput) {
        nameInput.addEventListener("input", function() {
            if (!slugInput.value) {
                generateSlug(this.value, "slug");
            }
        });
    }
    
    if (editNameInput && editSlugInput) {
        editNameInput.addEventListener("input", function() {
            if (!editSlugInput.value) {
                generateSlug(this.value, "edit_slug");
            }
        });
    }
}

function generateSlug(text, targetId) {
    const slug = text
        .toLowerCase()
        .replace(/[^\w\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/--+/g, "-")
        .trim();
    document.getElementById(targetId).value = slug;
}

// Initialize image previews
function initializeImagePreviews() {
    const imageUrlInput = document.getElementById("image_url");
    const editImageUrlInput = document.getElementById("edit_image_url");
    
    if (imageUrlInput) {
        imageUrlInput.addEventListener("input", function() {
            const preview = document.getElementById("image_url_preview");
            const container = document.getElementById("image_url_preview_container");
            updateImagePreview(this.value, preview, container);
        });
    }
    
    if (editImageUrlInput) {
        editImageUrlInput.addEventListener("input", function() {
            const preview = document.getElementById("edit_image_url_preview");
            const container = document.getElementById("edit_image_url_preview_container");
            updateImagePreview(this.value, preview, container);
        });
    }
}

function updateImagePreview(imageUrl, previewElement, containerElement) {
    if (!previewElement || !containerElement) return;
    
    if (imageUrl && imageUrl.trim() !== "") {
        let fullUrl = imageUrl;
        if (!fullUrl.startsWith("http://") && !fullUrl.startsWith("https://") && !fullUrl.startsWith("/")) {
            fullUrl = window.SITE_URL + fullUrl;
        }
        previewElement.src = fullUrl;
        containerElement.style.display = "block";
        
        // Add error handler for broken images
        previewElement.onerror = function() {
            this.src = window.SITE_URL + "assets/images/placeholder.jpg";
        };
    } else {
        containerElement.style.display = "none";
    }
}

// Edit category - Load category data via AJAX
function editCategory(categoryId) {
    console.log("Editing category ID:", categoryId);
    
    // Show loading in edit modal
    const editModal = document.getElementById("editCategoryModal");
    if (!editModal) {
        console.error("Edit modal not found!");
        alert("Error: Edit modal not found. Please refresh the page.");
        return;
    }
    
    const modal = new bootstrap.Modal(editModal);
    
    // Clear previous data
    const fields = [
        "edit_category_id",
        "edit_name", 
        "edit_slug",
        "edit_description",
        "edit_image_url",
        "edit_parent_id"
    ];
    
    fields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) element.value = "";
    });
    
    const isActiveCheckbox = document.getElementById("edit_is_active");
    if (isActiveCheckbox) isActiveCheckbox.checked = false;
    
    // Clear preview
    const previewContainer = document.getElementById("edit_image_url_preview_container");
    if (previewContainer) previewContainer.style.display = "none";
    
    // Show loading state
    const modalBody = editModal.querySelector(".modal-body");
    if (!modalBody) {
        console.error("Modal body not found!");
        return;
    }
    
    const originalContent = modalBody.innerHTML;
    modalBody.innerHTML = '<div class="text-center py-4">' +
                         '<div class="spinner-border text-dark" role="status">' +
                         '<span class="visually-hidden">Loading...</span>' +
                         '</div>' +
                         '<p class="mt-2">Loading category data...</p>' +
                         '</div>';
    
    modal.show();
    
    // Test the URL
    const url = window.SITE_URL + 'admin/api/category.php?id=' + categoryId;
    console.log("Fetching from URL:", url);
    
    // Fetch category data via AJAX
    fetch(url)
        .then(response => {
            console.log("Response status:", response.status, response.statusText);
            
            // First, check if response is JSON
            const contentType = response.headers.get("content-type");
            
            if (!contentType || !contentType.includes("application/json")) {
                // Response is not JSON, get it as text to see what it is
                return response.text().then(text => {
                    console.error("Response is not JSON. Received:", text.substring(0, 200));
                    throw new Error("Server returned non-JSON response. Check the AJAX endpoint.");
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log("JSON Response data:", data);
            if (data.success && data.category) {
                // Restore modal content
                modalBody.innerHTML = originalContent;
                
                // Populate form fields
                const category = data.category;
                document.getElementById("edit_category_id").value = category.id;
                document.getElementById("edit_name").value = category.name;
                document.getElementById("edit_slug").value = category.slug;
                document.getElementById("edit_description").value = category.description || "";
                document.getElementById("edit_image_url").value = category.image_url || "";
                document.getElementById("edit_parent_id").value = category.parent_id || "";
                
                const checkbox = document.getElementById("edit_is_active");
                if (checkbox) checkbox.checked = category.is_active == 1;
                
                // Show image preview if exists
                if (category.image_url) {
                    updateImagePreview(
                        category.image_url,
                        document.getElementById("edit_image_url_preview"),
                        document.getElementById("edit_image_url_preview_container")
                    );
                }
                
                console.log("Form populated successfully!");
            } else {
                throw new Error(data.error || "Failed to load category data");
            }
        })
        .catch(error => {
            console.error("Error loading category:", error);
            modalBody.innerHTML = '<div class="alert alert-danger">' +
                                '<h5>Error Loading Category</h5>' +
                                '<p>Failed to load category data. Please check the following:</p>' +
                                '<ol class="text-start">' +
                                '<li>Make sure the AJAX endpoint exists: <code>get-category.php</code></li>' +
                                '<li>Check browser console for detailed errors</li>' +
                                '<li>Try refreshing the page</li>' +
                                '</ol>' +
                                '<p><small>Error: ' + error.message + '</small></p>' +
                                '<button class="btn btn-outline-danger mt-2" onclick="editCategory(' + categoryId + ')">' +
                                '<i class="fas fa-redo me-1"></i> Retry' +
                                '</button>' +
                                '</div>';
        });
}

// Delete category confirmation
function initializeDeleteButtons() {
    document.querySelectorAll(".confirm-delete-category").forEach(button => {
        button.addEventListener("click", function() {
            const categoryId = this.dataset.id;
            const categoryName = this.dataset.name;
            const productCount = this.dataset.products;
            const subcategoryCount = this.dataset.subcategories;
            
            let message = 'Are you sure you want to delete "' + categoryName + '"?';
            
            if (parseInt(productCount) > 0) {
                message += '<br><br>This category has ' + productCount + ' product(s). They will become uncategorized.';
            }
            
            if (parseInt(subcategoryCount) > 0) {
                message += '<br><br>This category has ' + subcategoryCount + ' subcategory(s). They will lose their parent.';
            }
            
            Swal.fire({
                title: 'Delete Category?',
                html: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_category_id').value = categoryId;
                    document.getElementById('deleteCategoryForm').submit();
                }
            });
        });
    });
}

// Browse media function with actual upload
function browseMedia(targetField) {
    console.log('Opening file upload for field:', targetField);
    
    // Create a file input element
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.style.display = 'none';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Show loading on button
        const button = document.querySelector('button[onclick*="' + targetField + '"]');
        const originalHTML = button ? button.innerHTML : '<i class="fas fa-image"></i>';
        
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            button.disabled = true;
        }
        
        // Create form data for upload
        const formData = new FormData();
        formData.append('image', file);
        
        // Use absolute URL to avoid issues
        const uploadUrl = window.location.origin + '/linen-closet/admin/ajax/upload-category-image.php';
        console.log('Uploading to:', uploadUrl);
        
        fetch(uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Upload response status:', response.status);
            
            // Check content type
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);
            
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Upload returned non-JSON:', text.substring(0, 200));
                    throw new Error('Server returned HTML instead of JSON. Check upload endpoint.');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Upload response data:', data);
            if (data.success) {
                // Set the filepath in the input field
                document.getElementById(targetField).value = data.filepath;
                
                // Trigger preview update
                const event = new Event('input', { bubbles: true });
                document.getElementById(targetField).dispatchEvent(event);
                
                // Show success message
                Swal.fire({
                    title: 'Success!',
                    text: data.message || 'Image uploaded successfully',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            
            let errorMessage = error.message;
            if (error.message.includes('Failed to fetch')) {
                errorMessage = 'Cannot connect to server. The upload endpoint may not exist.';
            } else if (error.message.includes('HTML instead of JSON')) {
                errorMessage = 'Server error. There might be a PHP error in the upload script.';
            }
            
            Swal.fire({
                title: 'Upload Failed',
                html: '<div style="text-align: left;">' +
                    '<p><strong>' + errorMessage + '</strong></p>' +
                    '<p class="small text-muted">Technical details: ' + error.message + '</p>' +
                    '<p class="small">You can still enter the image path manually.</p>' +
                    '</div>',
                icon: 'error',
                confirmButtonText: 'Enter Path Manually',
                showCancelButton: true,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const defaultName = 'uploads/categories/' + file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
                    const manualPath = prompt('Enter image path:', defaultName);
                    if (manualPath) {
                        document.getElementById(targetField).value = manualPath;
                        const event = new Event('input', { bubbles: true });
                        document.getElementById(targetField).dispatchEvent(event);
                    }
                }
            });
        })
        .finally(() => {
            // Restore button
            if (button) {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }
        });
    };
    
    // Trigger file selection
    document.body.appendChild(input);
    input.click();
    document.body.removeChild(input);
}

// Simple fallback upload function (no AJAX)
function browseMediaSimple(targetField) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.style.display = 'none';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Create a blob URL for preview
        const blobUrl = URL.createObjectURL(file);
        
        // Set filename
        const fileName = 'uploads/categories/' + file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
        document.getElementById(targetField).value = fileName;
        
        // Show preview
        const preview = document.getElementById(targetField + '_preview') || 
                       document.getElementById('edit_image_url_preview') || 
                       document.getElementById('image_url_preview');
        const container = document.getElementById(targetField + '_preview_container') || 
                         document.getElementById('edit_image_url_preview_container') || 
                         document.getElementById('image_url_preview_container');
        
        if (preview && container) {
            preview.src = blobUrl;
            container.style.display = 'block';
        }
        
        // Clean up
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        
        Swal.fire({
            title: 'File Selected',
            text: 'Note: File is not uploaded yet. Save the category to upload.',
            icon: 'info',
            timer: 3000,
            showConfirmButton: false
        });
    };
    
    document.body.appendChild(input);
    input.click();
    document.body.removeChild(input);
}

// Initialize DataTables
function initializeDataTables() {
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('.data-table').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [7] } // Actions column
            ],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search categories...'
            }
        });
    }
}

// Initialize everything when DOM is loaded
document.addEventListener("DOMContentLoaded", function() {
    console.log("Categories page loaded");
    
    // Set SITE_URL from PHP if available
    if (typeof window.SITE_URL === 'undefined' && typeof SITE_URL !== 'undefined') {
        window.SITE_URL = SITE_URL;
    }
    
    console.log("SITE_URL:", window.SITE_URL);
    
    // Initialize functions
    checkEditFormElements();
    initializeSlugGeneration();
    initializeImagePreviews();
    initializeDeleteButtons();
    initializeDataTables();
});