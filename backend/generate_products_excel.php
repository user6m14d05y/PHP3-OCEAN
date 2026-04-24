<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('San Pham Import');

$headers = [
    'TÊN SẢN PHẨM (*)','LOẠI SP (*)','MÃ DANH MỤC (*)','MÃ THƯƠNG HIỆU',
    'MÔ TẢ NGẮN','MÔ TẢ CHI TIẾT','TRẠNG THÁI','NỔI BẬT',
    'ẢNH CHÍNH (URL)','ẢNH PHỤ (URLs)','MÀU SẮC','KÍCH CỠ',
    'GIÁ BÁN (*)','GIÁ GỐC','SỐ LƯỢNG KHO (*)','ẢNH BIẾN THỂ (URLs)',
];
foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . '1', $header);
}
$sheet->getStyle('A1:P1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0277BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);
$widths = ['A'=>30,'B'=>12,'C'=>14,'D'=>16,'E'=>30,'F'=>35,'G'=>14,'H'=>10,'I'=>15,'J'=>15,'K'=>14,'L'=>12,'M'=>14,'N'=>14,'O'=>16,'P'=>15];
foreach ($widths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

$categories = [1 => 'Áo', 2 => 'Quần', 3 => 'Phụ kiện'];

$productNames = [
    1 => [
        'Áo Thun Cổ Tròn Basic','Áo Polo Nam Cao Cấp','Áo Sơ Mi Trắng Công Sở',
        'Áo Khoác Gió Nhẹ','Áo Hoodie Oversize','Áo Thun Tay Dài Unisex',
        'Áo Croptop Nữ Trendy','Áo Blazer Nữ Thanh Lịch','Áo Len Cổ Lọ',
        'Áo Vest Nam Lịch Lãm','Áo Thun Họa Tiết Vintage','Áo Cardigan Len Mỏng',
        'Áo Khoác Bomber','Áo Denim Jean Wash','Áo Tank Top Thể Thao',
        'Áo Thun Local Brand','Áo Polo Kẻ Sọc','Áo Sơ Mi Flannel',
        'Áo Khoác Dạ Dài','Áo Nỉ Bông Mùa Đông','Áo Thun In Logo',
        'Áo Polo Pique Cotton','Áo Khoác Jean Unisex','Áo Thun Raglan',
        'Áo Sơ Mi Linen Hè','Áo Khoác Parka','Áo Thun Oversized',
        'Áo Hoodie Zip','Áo Polo Sport','Áo Gilet Phao Nhẹ',
        'Áo Thun Cổ Tim','Áo Khoác Chống Nắng UV','Áo Sơ Mi Caro',
        'Áo Thun Acid Wash','Áo Nỉ Unisex Basic','Áo Khoác Coach Jacket',
        'Áo Polo Ralph Style','Áo Thun Tie Dye','Áo Sơ Mi Oxford',
        'Áo Khoác Teddy Bear','Áo Thun Boxy Fit','Áo Polo Thêu Logo',
        'Áo Khoác Varsity','Áo Sơ Mi Satin','Áo Thun Graphic Tee',
        'Áo Sweater Knit','Áo Khoác MA-1','Áo Polo Dài Tay',
        'Áo Thun Henley','Áo Khoác Track Jacket','Áo Sơ Mi Hawaii',
        'Áo Thun Striped','Áo Polo Premium','Áo Khoác Fleece',
        'Áo Thun Muscle Fit','Áo Sơ Mi Slim Fit','Áo Khoác Windbreaker',
        'Áo Thun Modal Mát','Áo Polo Dry Fit','Áo Khoác Quilted',
        'Áo Thun Cotton Supima','Áo Sơ Mi Oversize','Áo Khoác Corduroy',
        'Áo Thun Bamboo Fiber','Áo Polo Jersey','Áo Khoác Trench Coat',
        'Áo Thun Coolmax','Áo Sơ Mi Denim','Áo Khoác Anorak',
        'Áo Thun Tech Fabric',
    ],
    2 => [
        'Quần Jean Slim Fit','Quần Tây Công Sở','Quần Short Kaki',
        'Quần Jogger Thể Thao','Quần Baggy Nữ','Quần Jean Rách Gối',
        'Quần Âu Nam Cao Cấp','Quần Short Thể Thao','Quần Legging Nữ',
        'Quần Kaki Ống Suông','Quần Jean Ống Rộng','Quần Cargo Túi Hộp',
        'Quần Tây Ống Đứng','Quần Short Linen','Quần Jogger Nỉ',
        'Quần Jean Skinny','Quần Short Đùi Basic','Quần Culottes Nữ',
        'Quần Âu Xếp Ly','Quần Jean Boyfriend','Quần Cargo Dài',
        'Quần Short Jean','Quần Tây Slim','Quần Jogger Kaki',
        'Quần Jean Boot Cut','Quần Short Swim','Quần Linen Ống Rộng',
        'Quần Jean Straight','Quần Short Cargo','Quần Chinos Nam',
        'Quần Jean Mom Fit','Quần Short Nỉ','Quần Palazzo Nữ',
        'Quần Jean Wash Đậm','Quần Short Quick Dry','Quần Tây Ống Loe',
        'Quần Jean Tapered','Quần Short Running','Quần Jogger Zip',
        'Quần Jean Regular','Quần Short Terry','Quần Tây Xếp Ly Đôi',
        'Quần Jean Crop','Quần Short Board','Quần Cargo Jogger',
        'Quần Jean Relaxed','Quần Short Golf','Quần Linen Đũi',
        'Quần Jean Loose Fit','Quần Short Chino','Quần Tây Flannel',
        'Quần Jean Dark Wash','Quần Short Mesh','Quần Jogger Techwear',
        'Quần Jean Light Wash','Quần Short Fleece','Quần Tây Wool Blend',
        'Quần Jean Selvedge','Quần Short Seersucker','Quần Cargo Slim',
        'Quần Jean Raw Denim','Quần Short Corduroy','Quần Jogger Premium',
        'Quần Jean Stretch','Quần Short Poplin','Quần Tây Cotton',
        'Quần Jean Vintage','Quần Short Hybrid','Quần Chinos Stretch',
        'Quần Jean Acid Wash',
    ],
    3 => [
        'Mũ Lưỡi Trai Thêu','Túi Tote Vải Canvas','Thắt Lưng Da Bò',
        'Ví Da Nam Compact','Kính Mát UV400','Khăn Choàng Lụa',
        'Balo Laptop Chống Nước','Mũ Bucket Hat Unisex','Túi Đeo Chéo Mini',
        'Găng Tay Da Mùa Đông','Vớ Cổ Cao Cotton','Dây Nịt Tự Động',
        'Túi Clutch Dự Tiệc','Mũ Beanie Len','Nhẫn Thép Titan',
        'Vòng Tay Charm Bạc','Khăn Quàng Cashmere','Túi Bao Tử Trendy',
        'Mũ Dad Hat Vintage','Kính Mát Aviator','Thắt Lưng Canvas',
        'Ví Dài Nữ Thanh Lịch','Balo Mini Nữ','Túi Crossbody Nam',
        'Mũ Snapback Logo','Vớ Thể Thao Ankle','Dây Chuyền Bạc 925',
        'Túi Laptop Sleeve','Mũ Fisherman','Kính Mát Cat Eye',
        'Thắt Lưng Braided','Ví Card Holder','Balo Du Lịch 30L',
        'Túi Gym Duffel','Mũ Visor Thể Thao','Vớ Dài Kẻ Sọc',
        'Khăn Bandana Cotton','Túi Shopper Tote','Mũ Newsboy Cap',
        'Kính Mát Wayfarer','Thắt Lưng Reversible','Ví Zipper Dài',
        'Balo Rolltop','Túi Wristlet Nữ','Mũ Trucker Mesh',
        'Vớ Lười No Show','Dây Chuyền Mạ Vàng','Túi Belt Bag',
        'Mũ Beret Nỉ','Kính Mát Round','Thắt Lưng Pin Buckle',
        'Ví Money Clip','Balo Drawstring','Túi Satchel Da',
        'Mũ Baseball Wash','Vớ Crew Cotton','Khăn Muffler Len',
        'Túi Pouch Đa Năng','Mũ Fedora','Kính Mát Shield',
        'Thắt Lưng Double Ring','Ví Bifold Classic','Balo Sling Bag',
        'Túi Messenger Canvas','Mũ Boonie Hat','Vớ Thermal Giữ Ấm',
        'Khăn Scarf Hoa Văn','Túi Envelope Clutch','Balo Anti-theft',
        'Mũ Cloche Vintage',
    ],
];

$colors = ['Đen','Trắng','Be','Xám','Xanh Navy','Đỏ','Xanh Rêu','Nâu','Hồng','Xanh Dương'];
$sizes = ['S','M','L','XL','XXL'];

$row = 2;

// ===== 100 SIMPLE =====
$si = [1=>0, 2=>0, 3=>0];
for ($i = 0; $i < 100; $i++) {
    $catId = $i < 34 ? 1 : ($i < 67 ? 2 : 3);
    $idx = $si[$catId]++;
    $name = $productNames[$catId][$idx] ?? ($categories[$catId] . ' SP' . ($i+1));
    $price = rand(89,1200)*1000;
    $compare = round($price * (1 + rand(15,40)/100), -3);

    $sheet->setCellValue("A{$row}", $name);
    $sheet->setCellValue("B{$row}", 'simple');
    $sheet->setCellValue("C{$row}", $catId);
    $sheet->setCellValue("D{$row}", '');
    $sheet->setCellValue("E{$row}", 'Sản phẩm chất lượng cao, thiết kế hiện đại phù hợp mọi phong cách');
    $sheet->setCellValue("F{$row}", '<p>Sản phẩm được làm từ chất liệu cao cấp, form dáng chuẩn. Cam kết hàng chính hãng.</p>');
    $sheet->setCellValue("G{$row}", 'active');
    $sheet->setCellValue("H{$row}", rand(0,4)===0 ? 1 : 0);
    $sheet->setCellValue("I{$row}", '');
    $sheet->setCellValue("J{$row}", '');
    $sheet->setCellValue("K{$row}", '');
    $sheet->setCellValue("L{$row}", '');
    $sheet->setCellValue("M{$row}", $price);
    $sheet->setCellValue("N{$row}", $compare);
    $sheet->setCellValue("O{$row}", rand(10,200));
    $sheet->setCellValue("P{$row}", '');
    if ($i%2===0) $sheet->getStyle("A{$row}:P{$row}")->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF3E0']]]);
    $row++;
}

// ===== 100 VARIANT =====
$vi = [1=>34, 2=>33, 3=>33];
for ($i = 0; $i < 100; $i++) {
    $catId = $i < 34 ? 1 : ($i < 67 ? 2 : 3);
    $idx = $vi[$catId]++;
    $name = $productNames[$catId][$idx] ?? ($categories[$catId] . ' VR' . ($i+1));
    $basePrice = rand(150,1500)*1000;
    $featured = rand(0,4)===0 ? 1 : 0;
    $numVar = rand(2,4);

    $sc = $colors; shuffle($sc); $selColors = array_slice($sc, 0, rand(1,2));
    if ($catId==3) { $selSizes = ['Free Size']; } else { $ss=$sizes; shuffle($ss); $selSizes = array_slice($ss, 0, rand(1,3)); }

    $vars = [];
    foreach ($selColors as $c) { foreach ($selSizes as $s) { $vars[] = ['c'=>$c,'s'=>$s]; if(count($vars)>=$numVar) break 2; } }

    $first = true; $startRow = $row;
    foreach ($vars as $v) {
        $vp = $basePrice + rand(-2,5)*10000;
        if($vp<50000) $vp=$basePrice;
        $vc = round($vp*(1+rand(15,40)/100), -3);

        $sheet->setCellValue("A{$row}", $name);
        if ($first) {
            $sheet->setCellValue("B{$row}", 'variant');
            $sheet->setCellValue("C{$row}", $catId);
            $sheet->setCellValue("D{$row}", '');
            $sheet->setCellValue("E{$row}", 'Thiết kế đa dạng màu sắc và kích cỡ, chất liệu premium');
            $sheet->setCellValue("F{$row}", '<p>Sản phẩm có nhiều biến thể màu sắc và size. Chất liệu cao cấp, bền đẹp.</p>');
            $sheet->setCellValue("G{$row}", 'active');
            $sheet->setCellValue("H{$row}", $featured);
            $first = false;
        } else {
            for ($c=1;$c<=9;$c++) $sheet->setCellValue(chr(65+$c).$row, '');
        }
        $sheet->setCellValue("I{$row}", '');
        $sheet->setCellValue("J{$row}", '');
        $sheet->setCellValue("K{$row}", $v['c']);
        $sheet->setCellValue("L{$row}", $v['s']);
        $sheet->setCellValue("M{$row}", $vp);
        $sheet->setCellValue("N{$row}", $vc);
        $sheet->setCellValue("O{$row}", rand(5,80));
        $sheet->setCellValue("P{$row}", '');
        $row++;
    }
    $endRow=$row-1;
    $bg = ($i%2===0) ? 'E3F2FD' : 'E8F5E9';
    $sheet->getStyle("A{$startRow}:P{$endRow}")->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]]]);
}

$outputPath = __DIR__ . '/200_san_pham_import.xlsx';
(new Xlsx($spreadsheet))->save($outputPath);
echo "✅ File: {$outputPath}\n📊 200 SP (100 simple + 100 variant) | Dòng: ".($row-2)."\n";
