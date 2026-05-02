function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('dropdownLoginForm');
    const errorContainer = document.getElementById('loginErrorContainer');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (errorContainer) {
                errorContainer.style.display = 'none';
                errorContainer.textContent = '';
            }

            const formData = new FormData(loginForm);
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.textContent : 'Login';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Logging in...';
            }

            fetch('ajax_login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    if (errorContainer) {
                        errorContainer.textContent = data.message || 'Invalid credentials';
                        errorContainer.style.display = 'block';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                if (errorContainer) {
                    errorContainer.textContent = 'An error occurred. Please try again.';
                    errorContainer.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            });
        });
    }

    initAutocomplete();
});

function initAutocomplete() {
    const searchInput = document.getElementById('donorSearch');
    const hiddenInput = document.getElementById('donorId');
    const suggestionBox = document.getElementById('donorSuggestions');
    const donors = window.activeDonors || [];

    if (!searchInput || !hiddenInput || !suggestionBox || !donors.length) return;

    let highlightedIndex = -1;
    let currentMatches = [];

    function renderSuggestions(matches) {
        suggestionBox.innerHTML = '';
        highlightedIndex = -1;
        currentMatches = matches;

        if (matches.length === 0) {
            suggestionBox.innerHTML = '<div class="autocomplete-no-results">No donors found</div>';
            suggestionBox.classList.add('visible');
            return;
        }

        matches.forEach(function(donor, index) {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.dataset.index = index;
            div.dataset.donorId = donor.donorId;
            div.textContent = donor.username + ' (' + donor.bloodGroup + donor.rhFactor + ')';
            suggestionBox.appendChild(div);
        });

        suggestionBox.classList.add('visible');
    }

    function filterDonors(query) {
        const lowerQuery = query.toLowerCase();
        return donors.filter(function(donor) {
            return donor.username.toLowerCase().includes(lowerQuery) ||
                   (donor.bloodGroup + donor.rhFactor).toLowerCase().includes(lowerQuery) ||
                   donor.donorId.toLowerCase().includes(lowerQuery);
        });
    }

    function clearSelection() {
        hiddenInput.value = '';
        const bloodGroupField = document.getElementById('bloodGroup');
        if (bloodGroupField) {
            bloodGroupField.value = '';
        }
    }

    function selectDonor(donor) {
        searchInput.value = donor.username + ' (' + donor.bloodGroup + donor.rhFactor + ')';
        hiddenInput.value = donor.donorId;
        suggestionBox.classList.remove('visible');
        highlightedIndex = -1;
        const bloodGroupField = document.getElementById('bloodGroup');
        if (bloodGroupField) {
            bloodGroupField.value = donor.bloodGroup + donor.rhFactor;
        }
    }

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        if (query.length === 0) {
            suggestionBox.classList.remove('visible');
            clearSelection();
            return;
        }
        renderSuggestions(filterDonors(query));
    });

    searchInput.addEventListener('keydown', function(e) {
        if (!suggestionBox.classList.contains('visible')) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = (highlightedIndex + 1) % currentMatches.length;
            updateHighlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = (highlightedIndex - 1 + currentMatches.length) % currentMatches.length;
            updateHighlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightedIndex >= 0 && currentMatches[highlightedIndex]) {
                selectDonor(currentMatches[highlightedIndex]);
            }
        } else if (e.key === 'Escape') {
            suggestionBox.classList.remove('visible');
            highlightedIndex = -1;
        }
    });

    function updateHighlight() {
        const items = suggestionBox.querySelectorAll('.autocomplete-item');
        items.forEach(function(item, idx) {
            if (idx === highlightedIndex) {
                item.classList.add('highlighted');
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    suggestionBox.addEventListener('click', function(e) {
        const item = e.target.closest('.autocomplete-item');
        if (item && item.dataset.index) {
            const index = parseInt(item.dataset.index, 10);
            if (currentMatches[index]) {
                selectDonor(currentMatches[index]);
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionBox.contains(e.target)) {
            suggestionBox.classList.remove('visible');
        }
    });

    const donationForm = document.getElementById('donationForm');
    if (donationForm) {
        donationForm.addEventListener('submit', function(e) {
            if (!hiddenInput.value) {
                e.preventDefault();
                alert('Please select a valid donor from the suggestions.');
                searchInput.focus();
            }
        });
    }
}
