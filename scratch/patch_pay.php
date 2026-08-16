<?php
$file = 'd:/xaamp/htdocs/Ridezo/booking/pay.php';
$content = file_get_contents($file);

$header_php = "<?php
session_start();
require '../app/config.php';

if (!isset(\$_SESSION['user_id'])) {
    header('Location: ../app/login.php');
    exit();
}

if (!isset(\$_GET['id']) || empty(\$_GET['id']) || !isset(\$_POST['pickup']) || !isset(\$_POST['return'])) {
    header('Location: ../main/index.php');
    exit();
}

\$vehicle_id = intval(\$_GET['id']);
\$pickup = \$_POST['pickup'];
\$return = \$_POST['return'];

\$stmt = \$conn->prepare(\"SELECT v.*, u.name as seller_name FROM vehicles v JOIN users u ON v.seller_id = u.user_id WHERE v.vehicle_id = ?\");
\$stmt->bind_param(\"i\", \$vehicle_id);
\$stmt->execute();
\$result = \$stmt->get_result();

if (\$result->num_rows === 0) {
    header(\"Location: ../main/index.php\");
    exit();
}
\$vehicle = \$result->fetch_assoc();
\$stmt->close();

\$image_path = strpos(\$vehicle['image_url'], 'http') === 0 ? \$vehicle['image_url'] : '../app/' . \$vehicle['image_url'];

\$start_date = new DateTime(\$pickup);
\$end_date = new DateTime(\$return);
\$days = \$start_date->diff(\$end_date)->days;
if (\$days < 1) \$days = 1;

\$subtotal = \$days * \$vehicle['rental_price_per_day'];
\$gst = \$subtotal * 0.18;
\$total = \$subtotal + \$gst;

?>
<!DOCTYPE html>";

$content = preg_replace('/<!DOCTYPE html>/i', $header_php, $content, 1);

// Replace fixed BMW X5 references in the page
$content = preg_replace('/<p id="txnIdDisplay"(.*?)><\/p>/', '<p id="txnIdDisplay"$1></p>', $content);
$content = str_replace('<strong style="color:#2563eb;" id="successCar">BMW X5</strong>', '<strong style="color:#2563eb;" id="successCar"><?php echo htmlspecialchars($vehicle[\'brand\'].\' \'.$vehicle[\'model\']); ?></strong>', $content);
$content = str_replace('<span style="color:#1e293b;font-weight:700;">BMW X5 (2006)</span>', '<span style="color:#1e293b;font-weight:700;"><?php echo htmlspecialchars($vehicle[\'brand\'].\' \'.$vehicle[\'model\'].\' (\'.$vehicle[\'year\'].\')\'); ?></span>', $content);
$content = str_replace('<span style="color:#1e293b;font-weight:700;">3 Days</span>', '<span style="color:#1e293b;font-weight:700;"><?php echo $days; ?> Days</span>', $content);
$content = str_replace('<span style="color:#2563eb;font-weight:800;font-size:18px;">₹10,620</span>', '<span style="color:#2563eb;font-weight:800;font-size:18px;">₹<?php echo number_format($total, 2); ?></span>', $content);


// Replace static Summary Card right panel
$content = str_replace('alt="BMW X5"', 'alt="<?php echo htmlspecialchars($vehicle[\'brand\'].\' \'.$vehicle[\'model\']); ?>"', $content);
$content = str_replace('src="https://images.unsplash.com/photo-1555215695-3004980ad54e?q=80&w=800"', 'src="<?php echo $image_path; ?>"', $content);
$content = str_replace('<p style="color:white;font-weight:800;font-size:17px;margin:0 0 2px;">BMW X5</p>', '<p style="color:white;font-weight:800;font-size:17px;margin:0 0 2px;"><?php echo htmlspecialchars($vehicle[\'brand\'].\' \'.$vehicle[\'model\']); ?></p>', $content);
$content = str_replace('<p style="color:#93c5fd;font-size:13px;font-weight:600;margin:0;">SUV · 2006</p>', '<p style="color:#93c5fd;font-size:13px;font-weight:600;margin:0;"><?php echo htmlspecialchars($vehicle[\'vehicle_type\'].\' &bull; \'.$vehicle[\'year\']); ?></p>', $content);
$content = str_replace('<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;">New York</p>', '<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;"><?php echo htmlspecialchars($vehicle[\'area\']); ?></p>', $content);
$content = str_replace('<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;">May 10, 2025</p>', '<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;"><?php echo date(\'M d, Y\', strtotime($pickup)); ?></p>', $content);
$content = str_replace('<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;">May 13, 2025</p>', '<p style="font-size:13px;color:#1e293b;font-weight:700;margin:0;"><?php echo date(\'M d, Y\', strtotime($return)); ?></p>', $content);

$content = preg_replace('/<span style="color:#475569;font-weight:700;">₹3,000 × 3 days<\/span>/', '<span style="color:#475569;font-weight:700;">₹<?php echo number_format($vehicle[\'rental_price_per_day\'], 0); ?> × <?php echo $days; ?> days</span>', $content);
$content = preg_replace('/<span style="color:#475569;font-weight:700;">₹9,000<\/span>/', '<span style="color:#475569;font-weight:700;">₹<?php echo number_format($subtotal, 2); ?></span>', $content);
$content = preg_replace('/<span style="color:#475569;font-weight:700;">₹1,620<\/span>/', '<span style="color:#475569;font-weight:700;">₹<?php echo number_format($gst, 2); ?></span>', $content);
$content = preg_replace('/<span style="color:#2563eb;font-weight:800;font-size:24px;">₹10,620<\/span>/', '<span style="color:#2563eb;font-weight:800;font-size:24px;">₹<?php echo number_format($total, 2); ?></span>', $content);

$content = str_replace('Pay ₹10,620 Securely', 'Pay ₹<?php echo number_format($total, 2); ?> Securely', $content);

$js_replace = <<<'EOD'
        err.classList.remove('show');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';

        const payload = {
            vehicle_id: <?php echo $vehicle_id; ?>,
            pickup: '<?php echo $pickup; ?>',
            return_date: '<?php echo $return; ?>',
            total_amount: <?php echo $total; ?>
        };

        fetch('process_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('paymentForm').style.display = 'none';
                document.getElementById('successScreen').classList.add('show');
                document.getElementById('txnIdDisplay').innerText = data.txId;
                document.getElementById('successMethod').innerText = currentMethod.toUpperCase();
                window.scrollTo({top: 100, behavior: 'smooth'});
            } else {
                errTxt.innerText = data.message || "Payment Failed";
                err.classList.add('show');
                btn.disabled = false;
                btn.innerHTML = 'Pay ₹<?php echo number_format($total, 2); ?> Securely';
            }
        })
        .catch(error => {
            errTxt.innerText = "Network Error";
            err.classList.add('show');
            btn.disabled = false;
            btn.innerHTML = 'Pay ₹<?php echo number_format($total, 2); ?> Securely';
        });
EOD;

$content = preg_replace('/err\.classList\.remove\(\'show\'\);\s+btn\.disabled = true;\s+btn\.innerHTML = \'<span class="spinner"><\/span> Processing...\';\s+setTimeout\(\(\) => \{.*?\}, 2500\);/s', $js_replace, $content);

// Small fix for PHP inside JS string
$content = str_replace('<?php echo number_format($total, 2); ?>', "<?php echo number_format(\$total, 2); ?>", $content);

file_put_contents($file, $content);
echo "Patched successfully";
?>
