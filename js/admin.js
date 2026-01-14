/**
 * Track Tyler - Admin Panel JavaScript
 * Handles dynamic segment management and FlightAware URL parsing
 */

// Segment counters (initialized from PHP)
window.segmentCounters = window.segmentCounters || { departure: 0, return: 0 };

/**
 * Add a new segment to a direction
 */
function addSegment(direction) {
    const container = document.getElementById(`${direction}-segments`);
    const template = document.getElementById('segment-template');
    const emptyMsg = document.getElementById(`${direction}-empty`);

    // Hide empty message
    if (emptyMsg) {
        emptyMsg.style.display = 'none';
    }

    // Get current index
    const index = window.segmentCounters[direction];
    window.segmentCounters[direction]++;

    // Clone template and replace placeholders
    let html = template.innerHTML
        .replace(/\{\{DIRECTION\}\}/g, direction)
        .replace(/\{\{INDEX\}\}/g, index)
        .replace(/\{\{NUMBER\}\}/g, index + 1);

    // Create element and append
    const div = document.createElement('div');
    div.innerHTML = html;
    const segmentCard = div.firstElementChild;
    container.appendChild(segmentCard);

    // Focus on flight number input
    const flightInput = segmentCard.querySelector('input[name*="flight_number"]');
    if (flightInput) {
        flightInput.focus();
    }

    updateSegmentNumbers(direction);
}

/**
 * Remove a segment
 */
function removeSegment(button) {
    const segmentCard = button.closest('.segment-card');
    const container = segmentCard.parentElement;
    const direction = container.id.replace('-segments', '');

    segmentCard.remove();

    // Show empty message if no segments left
    const remainingSegments = container.querySelectorAll('.segment-card');
    if (remainingSegments.length === 0) {
        const emptyMsg = document.getElementById(`${direction}-empty`);
        if (emptyMsg) {
            emptyMsg.style.display = 'block';
        }
    }

    updateSegmentNumbers(direction);
}

/**
 * Update segment numbers after add/remove
 */
function updateSegmentNumbers(direction) {
    const container = document.getElementById(`${direction}-segments`);
    const segments = container.querySelectorAll('.segment-card');

    segments.forEach((segment, index) => {
        const numberSpan = segment.querySelector('.segment-number');
        const heading = segment.querySelector('h6');

        if (numberSpan) {
            numberSpan.textContent = index + 1;
        }

        if (heading) {
            heading.innerHTML = `<span class="segment-number">${index + 1}</span> Segment ${index + 1}`;
        }

        // Update sort order hidden field
        const sortOrderInput = segment.querySelector('input[name*="sort_order"]');
        if (sortOrderInput) {
            sortOrderInput.value = index;
        }
    });
}

/**
 * Parse FlightAware URL and populate segment fields
 */
async function parseFlightAwareUrl(button) {
    const inputGroup = button.closest('.input-group');
    const urlInput = inputGroup.querySelector('.fa-url');
    const url = urlInput.value.trim();

    if (!url) {
        alert('Please enter a FlightAware URL');
        urlInput.focus();
        return;
    }

    // Disable button while processing
    button.disabled = true;
    const originalHtml = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch('api/parse-flightaware.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `url=${encodeURIComponent(url)}`
        });

        const response_data = await response.json();

        if (!response_data.success) {
            throw new Error(response_data.error || 'Failed to parse URL');
        }

        // Get the segment card
        const segmentCard = button.closest('.segment-card');
        if (!segmentCard) {
            throw new Error('Could not find segment card');
        }

        // Populate flight number
        if (response_data.flight_number) {
            const flightInput = segmentCard.querySelector('input[name*="flight_number"]');
            if (flightInput) flightInput.value = response_data.flight_number;
        }

        // Populate airline name
        if (response_data.airline_name) {
            const airlineInput = segmentCard.querySelector('input[name*="airline_name"]');
            if (airlineInput) airlineInput.value = response_data.airline_name;
        }

        // Populate departure airport
        if (response_data.departure_airport) {
            const depInput = segmentCard.querySelector('input[name*="departure_airport"]');
            if (depInput) depInput.value = response_data.departure_airport;
        }

        // Populate arrival airport
        if (response_data.arrival_airport) {
            const arrInput = segmentCard.querySelector('input[name*="arrival_airport"]');
            if (arrInput) arrInput.value = response_data.arrival_airport;
        }

        // Set departure timezone
        if (response_data.departure_timezone) {
            const depTzSelect = segmentCard.querySelector('select[name*="departure_timezone"]');
            if (depTzSelect) depTzSelect.value = response_data.departure_timezone;
        }

        // Set arrival timezone
        if (response_data.arrival_timezone) {
            const arrTzSelect = segmentCard.querySelector('select[name*="arrival_timezone"]');
            if (arrTzSelect) arrTzSelect.value = response_data.arrival_timezone;
        }

        // Set scheduled departure (already converted to local time by PHP)
        if (response_data.scheduled_departure_local) {
            const depDatetimeInput = segmentCard.querySelector('input[name*="scheduled_departure"]');
            if (depDatetimeInput) depDatetimeInput.value = response_data.scheduled_departure_local;
        }

        // Set FlightAware URL field
        const faUrlInput = segmentCard.querySelector('input[name*="flightaware_url"]');
        if (faUrlInput) faUrlInput.value = url;

        // Show success feedback
        urlInput.classList.add('is-valid');
        setTimeout(() => urlInput.classList.remove('is-valid'), 2000);

        // Alert user about arrival time
        alert('✅ Parsed successfully!\n\nNote: Arrival time is not in the URL.\nPlease enter the scheduled arrival time manually.');

    } catch (error) {
        console.error('Parse error:', error);
        alert('Error: ' + error.message);
        urlInput.classList.add('is-invalid');
        setTimeout(() => urlInput.classList.remove('is-invalid'), 2000);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

/**
 * Handle form submission
 */
document.addEventListener('DOMContentLoaded', function() {
    const tripForm = document.getElementById('tripForm');

    if (tripForm) {
        tripForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = tripForm.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

            try {
                const formData = new FormData(tripForm);

                const response = await fetch(tripForm.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect to dashboard
                    window.location.href = 'index.php';
                } else {
                    throw new Error(data.error || 'Failed to save trip');
                }

            } catch (error) {
                console.error('Save error:', error);
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }
});

/**
 * Auto-suggest airline name based on flight number
 */
function suggestAirline(flightNumber) {
    const airlines = {
        'AA': 'American Airlines',
        'UA': 'United Airlines',
        'DL': 'Delta Air Lines',
        'WN': 'Southwest Airlines',
        'B6': 'JetBlue Airways',
        'AS': 'Alaska Airlines',
        'NK': 'Spirit Airlines',
        'F9': 'Frontier Airlines',
        'NH': 'All Nippon Airways',
        'JL': 'Japan Airlines',
        'KE': 'Korean Air',
        'CX': 'Cathay Pacific',
        'SQ': 'Singapore Airlines',
        'VN': 'Vietnam Airlines',
        'BR': 'EVA Air',
        'CI': 'China Airlines',
        'OZ': 'Asiana Airlines',
        'TG': 'Thai Airways',
        'MH': 'Malaysia Airlines'
    };

    // Extract airline code (first 2 letters)
    const match = flightNumber.match(/^([A-Z]{2})/i);
    if (match) {
        return airlines[match[1].toUpperCase()] || '';
    }
    return '';
}

// Add input event listener for flight number fields to auto-suggest airline
document.addEventListener('input', function(e) {
    if (e.target.name && e.target.name.includes('flight_number')) {
        const segmentCard = e.target.closest('.segment-card');
        if (segmentCard) {
            const airlineInput = segmentCard.querySelector('input[name*="airline_name"]');
            if (airlineInput && !airlineInput.value) {
                const suggested = suggestAirline(e.target.value);
                if (suggested) {
                    airlineInput.value = suggested;
                }
            }
        }
    }
});
