<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management System - Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center mb-0">Visitor Registration Form</h3>
                    </div>
                    <div class="card-body">
                        <form id="visitorForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="id_proof_type" class="form-label">ID Proof Type</label>
                                    <select class="form-select" id="id_proof_type" name="id_proof_type">
                                        <option value="">Select ID Type</option>
                                        <option value="aadhar">Aadhar Card</option>
                                        <option value="pan">PAN Card</option>
                                        <option value="driving">Driving License</option>
                                        <option value="passport">Passport</option>
                                        <option value="voter">Voter ID</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_proof_number" class="form-label">ID Proof Number</label>
                                    <input type="text" class="form-control" id="id_proof_number" name="id_proof_number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="visit_date" class="form-label">Visit Date *</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="expected_arrival" class="form-label">Expected Arrival Time</label>
                                    <input type="time" class="form-control" id="expected_arrival" name="expected_arrival">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="expected_departure" class="form-label">Expected Departure Time</label>
                                    <input type="time" class="form-control" id="expected_departure" name="expected_departure">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose of Visit *</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="2" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="person_to_meet" class="form-label">Person to Meet *</label>
                                    <input type="text" class="form-control" id="person_to_meet" name="person_to_meet" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Register Visitor</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="admin/login.php" class="btn btn-outline-primary">Admin Login</a>
                    <a href="gate/scanner.php" class="btn btn-outline-success">Gate Scanner</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Registration Successful!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Your registration has been submitted successfully. You will receive an email with QR code once approved by admin.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Processing your registration...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/registration.js"></script>
</body>
</html>
