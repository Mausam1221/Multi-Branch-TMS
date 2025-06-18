<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireRole('customer');

$package_id = $_GET['package_id'] ?? null;
if (!$package_id) {
    header('Location: customer.php');
    exit;
}

// Get package details
$package_query = "SELECT p.*, b.name as branch_name FROM packages p 
                  JOIN branches b ON p.branch_id = b.id 
                  WHERE p.id = ? AND p.status = 'active'";
$package_stmt = $db->prepare($package_query);
$package_stmt->execute([$package_id]);
$package = $package_stmt->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    header('Location: customer.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Package - TravelNepal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .booking-container {
            padding: 20px 15px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .booking-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .package-header {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .package-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .booking-form {
            padding: 30px;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .price-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .khalti-btn {
            background: #5c2d91;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .khalti-btn:hover {
            background: #4a2373;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(92, 45, 145, 0.3);
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: white;
            transform: scale(1.1);
            color: #667eea;
        }
    </style>
</head>
<body>
    <a href="customer.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="booking-container">
        <div class="booking-card">
            <div class="package-header">
                <h2><i class="fas fa-suitcase me-2"></i><?php echo $package['name']; ?></h2>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-1"></i><?php echo $package['destination']; ?></p>
            </div>
            
            <div class="row g-0">
                <div class="col-md-6">
                    <img src="<?php echo $package['image_url']; ?>" alt="<?php echo $package['name']; ?>" class="package-image">
                    
                    <div class="p-4">
                        <h5>Package Details</h5>
                        <p class="text-muted"><?php echo $package['description']; ?></p>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                                    <h6><?php echo $package['duration_days']; ?> Days</h6>
                                    <small class="text-muted">Duration</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-rupee-sign fa-2x text-success mb-2"></i>
                                    <h6>Rs.<?php echo number_format($package['price']); ?></h6>
                                    <small class="text-muted">Per Person</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-building me-1"></i>Managed by <?php echo $package['branch_name']; ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="booking-form">
                        <h4 class="mb-4"><i class="fas fa-calendar-plus me-2"></i>Book This Package</h4>
                        
                        <form id="bookingForm">
                            <input type="hidden" id="package_id" value="<?php echo $package['id']; ?>">
                            <input type="hidden" id="package_price" value="<?php echo $package['price']; ?>">
                            
                            <div class="form-floating">
                                <input type="date" class="form-control" id="travel_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <label for="travel_date">Travel Date</label>
                            </div>
                            
                            <div class="form-floating">
                                <select class="form-select" id="number_of_people" required onchange="calculateTotal()">
                                    <option value="">Select Number of People</option>
                                    <option value="1">1 Person</option>
                                    <option value="2">2 People</option>
                                    <option value="3">3 People</option>
                                    <option value="4">4 People</option>
                                    <option value="5">5 People</option>
                                    <option value="6">6 People</option>
                                    <option value="7">7 People</option>
                                    <option value="8">8 People</option>
                                    <option value="9">9 People</option>
                                    <option value="10">10 People</option>
                                </select>
                                <label for="number_of_people">Number of People</label>
                            </div>
                            
                            <div class="form-floating">
                                <textarea class="form-control" id="special_requests" style="height: 100px" placeholder="Any special requests..."></textarea>
                                <label for="special_requests">Special Requests (Optional)</label>
                            </div>
                            
                            <div class="price-summary">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Package Price:</span>
                                    <span>Rs.<?php echo number_format($package['price']); ?> per person</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Number of People:</span>
                                    <span id="people_count">0</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Total Amount:</span>
                                    <span id="total_amount">Rs.0</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="khalti-btn mt-3" id="bookBtn" disabled>
                                <i class="fas fa-credit-card me-2"></i>Pay with Khalti
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>Processing your booking...</h5>
                    <p class="text-muted">Please wait while we process your payment.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4>Booking Confirmed!</h4>
                    <p class="text-muted">Your booking has been confirmed and payment processed successfully.</p>
                    <div id="booking-details" class="mt-3"></div>
                    <button type="button" class="btn btn-primary mt-3" onclick="window.location.href='customer.php'">
                        Go to Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>
    <script>
        function calculateTotal() {
            const price = parseFloat(document.getElementById('package_price').value);
            const people = parseInt(document.getElementById('number_of_people').value) || 0;
            const total = price * people;
            
            document.getElementById('people_count').textContent = people;
            document.getElementById('total_amount').textContent = 'Rs.' + total.toLocaleString();
            
            const bookBtn = document.getElementById('bookBtn');
            if (people > 0 && document.getElementById('travel_date').value) {
                bookBtn.disabled = false;
            } else {
                bookBtn.disabled = true;
            }
        }
        
        document.getElementById('travel_date').addEventListener('change', calculateTotal);
        
        // Khalti Configuration
        var config = {
            "publicKey": "test_public_key_dc74e0fd57cb46cd93832aee0a390234", // Replace with your public key
            "productIdentity": "travel_package",
            "productName": "<?php echo $package['name']; ?>",
            "productUrl": window.location.href,
            "paymentPreference": [
                "KHALTI",
                "EBANKING",
                "MOBILE_BANKING",
                "CONNECT_IPS",
                "SCT",
            ],
            "eventHandler": {
                onSuccess(payload) {
                    console.log(payload);
                    verifyPayment(payload);
                },
                onError(error) {
                    console.log(error);
                    alert('Payment failed. Please try again.');
                },
                onClose() {
                    console.log('Payment widget closed');
                }
            }
        };
        
        var checkout = new KhaltiCheckout(config);
        
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                package_id: document.getElementById('package_id').value,
                travel_date: document.getElementById('travel_date').value,
                number_of_people: document.getElementById('number_of_people').value,
                special_requests: document.getElementById('special_requests').value
            };
            
            // Show loading modal
            new bootstrap.Modal(document.getElementById('loadingModal')).show();
            
            // Create booking first
            fetch('../api/create-booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                
                if (data.success) {
                    // Store booking ID for payment verification
                    window.currentBookingId = data.booking_id;
                    
                    // Open Khalti payment
                    checkout.show({
                        amount: data.total_amount * 100, // Amount in paisa
                    });
                } else {
                    alert('Error creating booking: ' + data.error);
                }
            })
            .catch(error => {
                bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                console.error('Error:', error);
                alert('Error creating booking. Please try again.');
            });
        });
        
        function verifyPayment(payload) {
            new bootstrap.Modal(document.getElementById('loadingModal')).show();
            
            fetch('../api/khalti-verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    token: payload.token,
                    amount: payload.amount,
                    booking_id: window.currentBookingId
                })
            })
            .then(response => response.json())
            .then(data => {
                bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                
                if (data.success) {
                    document.getElementById('booking-details').innerHTML = `
                        <div class="alert alert-success">
                            <strong>Booking ID:</strong> #${data.booking_id}<br>
                            <strong>Amount Paid:</strong> Rs.${(payload.amount/100).toLocaleString()}
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                } else {
                    alert('Payment verification failed: ' + data.error);
                }
            })
            .catch(error => {
                bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                console.error('Error:', error);
                alert('Payment verification failed. Please contact support.');
            });
        }
    </script>
</body>
</html>
