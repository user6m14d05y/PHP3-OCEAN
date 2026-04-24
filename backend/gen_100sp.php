<?php
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet()->setTitle('Import 100 SP');

$headers = ['TÊN SẢN PHẨM (*)','LOẠI SP (*)','MÃ DANH MỤC (*)','MÃ THƯƠNG HIỆU','MÔ TẢ NGẮN','MÔ TẢ CHI TIẾT','TRẠNG THÁI','NỔI BẬT','ẢNH CHÍNH (URL)','ẢNH PHỤ (URLs)','MÀU SẮC','KÍCH CỠ','GIÁ BÁN (*)','GIÁ GỐC','SỐ LƯỢNG KHO (*)','ẢNH BIẾN THỂ (URLs)'];
foreach ($headers as $c => $h) $sheet->setCellValue(chr(65+$c).'1', $h);
$sheet->getStyle('A1:P1')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>11],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0277BD']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);

// Color name -> hex for placeholder images
$colorHex = [
    'Đen'=>'222222','Trắng'=>'F0F0F0','Be'=>'D4C5A9','Xám'=>'808080',
    'Xanh Navy'=>'1B2A4A','Đỏ'=>'CC2936','Xanh Rêu'=>'4A6741',
    'Nâu'=>'7B3F00','Hồng'=>'E8909C','Xanh Dương'=>'2E86DE',
    'Cam'=>'E97420','Tím'=>'6C3483','Vàng'=>'D4AC0D','Kem'=>'F5E6CC',
];
$allColors = array_keys($colorHex);
$sizes = ['S','M','L','XL'];

// Product data: [name, catId, keyword_for_image, short_desc]
$products = [
    // === 50 SIMPLE ===
    ['Áo Thun Cổ Tròn Cotton',1,'cotton+tshirt','Áo thun cotton 100% mềm mại thoáng mát'],
    ['Áo Polo Nam Classic',1,'polo+shirt+men','Áo polo nam cổ bẻ thanh lịch'],
    ['Áo Sơ Mi Trắng Slim Fit',1,'white+dress+shirt','Áo sơ mi trắng form ôm công sở'],
    ['Áo Khoác Gió Unisex',1,'windbreaker+jacket','Áo khoác gió chống nước nhẹ'],
    ['Áo Hoodie Nỉ Bông',1,'hoodie+sweater','Áo hoodie nỉ bông ấm áp mùa đông'],
    ['Áo Len Cổ Lọ Premium',1,'turtleneck+sweater','Áo len cổ lọ cao cấp giữ ấm tốt'],
    ['Áo Blazer Nữ Công Sở',1,'blazer+women','Áo blazer nữ thanh lịch chuyên nghiệp'],
    ['Áo Tank Top Gym',1,'tank+top+sport','Áo tank top tập gym thoáng khí'],
    ['Áo Cardigan Len Mỏng',1,'cardigan+knit','Áo cardigan len mỏng dễ phối đồ'],
    ['Áo Vest Nam Lịch Lãm',1,'vest+formal+men','Áo vest nam phong cách lịch lãm'],
    ['Áo Croptop Nữ Trendy',1,'croptop+women','Áo croptop nữ phong cách trẻ trung'],
    ['Áo Thun Oversized Logo',1,'oversized+tshirt','Áo thun oversized in logo nổi bật'],
    ['Áo Khoác Bomber Phi Công',1,'bomber+jacket','Áo khoác bomber phong cách phi công'],
    ['Áo Sweater Knit Pattern',1,'knit+sweater','Áo sweater đan họa tiết tinh tế'],
    ['Áo Sơ Mi Linen Hè',1,'linen+shirt','Áo sơ mi linen thoáng mát mùa hè'],
    ['Áo Khoác Dạ Dài',1,'wool+coat','Áo khoác dạ dài sang trọng mùa đông'],
    ['Áo Thun Henley Basic',1,'henley+shirt','Áo thun henley cổ nút phong cách'],
    ['Quần Jean Slim Fit Nam',2,'slim+jeans+men','Quần jean slim fit co giãn thoải mái'],
    ['Quần Tây Công Sở Nam',2,'formal+trousers','Quần tây nam form đứng lịch sự'],
    ['Quần Short Kaki Hè',2,'khaki+shorts','Quần short kaki mát mẻ cho mùa hè'],
    ['Quần Jogger Thể Thao',2,'jogger+pants','Quần jogger thể thao năng động'],
    ['Quần Baggy Nữ Ống Rộng',2,'baggy+pants+women','Quần baggy nữ ống rộng thoải mái'],
    ['Quần Legging Tập Gym',2,'legging+gym','Quần legging co giãn 4 chiều tập gym'],
    ['Quần Cargo Túi Hộp',2,'cargo+pants','Quần cargo nhiều túi phong cách street'],
    ['Quần Âu Xếp Ly Nam',2,'pleated+trousers','Quần âu xếp ly nam cao cấp'],
    ['Quần Jean Rách Gối',2,'ripped+jeans','Quần jean rách gối phong cách cá tính'],
    ['Quần Short Thể Thao Dry',2,'sport+shorts','Quần short thể thao vải khô nhanh'],
    ['Quần Kaki Ống Suông',2,'straight+khaki','Quần kaki ống suông dáng chuẩn'],
    ['Quần Culottes Nữ',2,'culottes+women','Quần culottes nữ thanh lịch duyên dáng'],
    ['Quần Linen Đũi Hè',2,'linen+pants','Quần linen đũi mát mẻ thoáng khí'],
    ['Quần Jean Boyfriend Nữ',2,'boyfriend+jeans','Quần jean boyfriend nữ thoải mái'],
    ['Quần Palazzo Ống Rộng',2,'palazzo+pants','Quần palazzo ống rộng thời thượng'],
    ['Quần Chinos Nam Premium',2,'chinos+men','Quần chinos nam cao cấp dễ phối'],
    ['Mũ Lưỡi Trai Thêu Logo',3,'baseball+cap','Mũ lưỡi trai thêu logo phong cách'],
    ['Túi Tote Vải Canvas',3,'canvas+tote+bag','Túi tote vải canvas bền đẹp'],
    ['Thắt Lưng Da Bò Thật',3,'leather+belt','Thắt lưng da bò thật cao cấp'],
    ['Ví Da Nam Compact',3,'leather+wallet','Ví da nam nhỏ gọn nhiều ngăn'],
    ['Kính Mát Phân Cực UV400',3,'sunglasses','Kính mát chống UV400 thời trang'],
    ['Balo Laptop Chống Nước',3,'laptop+backpack','Balo laptop chống nước tiện dụng'],
    ['Mũ Bucket Hat Unisex',3,'bucket+hat','Mũ bucket unisex che nắng tốt'],
    ['Túi Đeo Chéo Mini',3,'crossbody+bag','Túi đeo chéo mini gọn nhẹ'],
    ['Khăn Choàng Lụa Cao Cấp',3,'silk+scarf','Khăn choàng lụa mềm mại sang trọng'],
    ['Vớ Cổ Cao Cotton',3,'cotton+socks','Vớ cổ cao cotton thấm hút tốt'],
    ['Dây Nịt Tự Động Nam',3,'automatic+belt','Dây nịt khóa tự động nam tiện lợi'],
    ['Túi Clutch Dự Tiệc',3,'clutch+bag','Túi clutch nữ dự tiệc sang trọng'],
    ['Mũ Beanie Len Ấm',3,'beanie+hat','Mũ beanie len giữ ấm mùa đông'],
    ['Vòng Tay Charm Bạc',3,'silver+bracelet','Vòng tay charm bạc thời trang'],
    ['Balo Mini Nữ Thời Trang',3,'mini+backpack+women','Balo mini nữ xinh xắn'],
    ['Túi Bao Tử Trendy',3,'fanny+pack','Túi bao tử phong cách trendy'],
    ['Dây Chuyền Bạc 925',3,'silver+necklace','Dây chuyền bạc 925 tinh tế'],
    // === 50 VARIANT ===
    ['Áo Thun Premium V-neck',1,'vneck+tshirt','Áo thun cổ tim chất liệu premium'],
    ['Áo Polo Sport Dry-Fit',1,'sport+polo','Áo polo thể thao thoáng khí Dry-Fit'],
    ['Áo Sơ Mi Oxford Classic',1,'oxford+shirt','Áo sơ mi oxford phong cách classic'],
    ['Áo Khoác Fleece Ấm',1,'fleece+jacket','Áo khoác fleece ấm áp co giãn'],
    ['Áo Hoodie Zip Premium',1,'zip+hoodie','Áo hoodie kéo khóa chất liệu premium'],
    ['Áo Thun Graphic Art',1,'graphic+tshirt','Áo thun in hình nghệ thuật độc đáo'],
    ['Áo Khoác Varsity College',1,'varsity+jacket','Áo khoác varsity phong cách college'],
    ['Áo Polo Pique Classic',1,'pique+polo','Áo polo pique cotton cổ điển'],
    ['Áo Sơ Mi Caro Flannel',1,'flannel+shirt','Áo sơ mi caro flannel ấm áp'],
    ['Áo Khoác Windbreaker Pro',1,'windbreaker','Áo khoác gió chuyên nghiệp'],
    ['Áo Thun Raglan Baseball',1,'raglan+tshirt','Áo thun raglan phong cách baseball'],
    ['Áo Nỉ Bông Unisex',1,'sweatshirt+unisex','Áo nỉ bông unisex ấm áp'],
    ['Áo Khoác Coach Street',1,'coach+jacket','Áo khoác coach phong cách đường phố'],
    ['Áo Polo Long Sleeve',1,'long+sleeve+polo','Áo polo tay dài thanh lịch'],
    ['Áo Sơ Mi Satin Nữ',1,'satin+blouse','Áo sơ mi satin nữ bóng mượt'],
    ['Áo Khoác Quilted Padded',1,'quilted+jacket','Áo khoác trần trám giữ ấm'],
    ['Áo Thun Tie-Dye Art',1,'tiedye+tshirt','Áo thun tie-dye nghệ thuật loang màu'],
    ['Quần Jean Straight Classic',2,'straight+jeans','Quần jean ống đứng cổ điển'],
    ['Quần Tây Slim Modern',2,'slim+trousers','Quần tây slim hiện đại thanh lịch'],
    ['Quần Short Swim Beach',2,'swim+shorts','Quần short bơi đi biển thoải mái'],
    ['Quần Jogger Zip Pocket',2,'jogger+zipper','Quần jogger túi khóa kéo tiện lợi'],
    ['Quần Jean Mom Fit Nữ',2,'mom+jeans','Quần jean mom fit nữ retro cổ điển'],
    ['Quần Short Cargo Outdoor',2,'cargo+shorts','Quần short cargo phong cách outdoor'],
    ['Quần Tây Wool Blend',2,'wool+trousers','Quần tây wool blend cao cấp mùa đông'],
    ['Quần Jean Skinny Stretch',2,'skinny+jeans','Quần jean skinny co giãn tối đa'],
    ['Quần Short Running Pro',2,'running+shorts','Quần short chạy bộ chuyên nghiệp'],
    ['Quần Cargo Slim Techwear',2,'techwear+cargo','Quần cargo slim phong cách techwear'],
    ['Quần Jean Boot Cut Retro',2,'bootcut+jeans','Quần jean ống loe phong cách retro'],
    ['Quần Jogger Nỉ Premium',2,'fleece+jogger','Quần jogger nỉ bông cao cấp'],
    ['Quần Short Linen Beach',2,'linen+shorts','Quần short linen thoáng mát đi biển'],
    ['Quần Jean Relaxed Wash',2,'relaxed+jeans','Quần jean relaxed fit wash nhẹ'],
    ['Quần Tây Xếp Ly Đôi',2,'double+pleat+pants','Quần tây xếp ly đôi lịch lãm'],
    ['Quần Short Terry Lounge',2,'terry+shorts','Quần short terry vải bông mềm mại'],
    ['Túi Crossbody Da PU',3,'crossbody+leather','Túi đeo chéo da PU thời trang'],
    ['Mũ Snapback Street',3,'snapback+cap','Mũ snapback phong cách đường phố'],
    ['Thắt Lưng Braided Woven',3,'braided+belt','Thắt lưng đan bện phong cách'],
    ['Ví Card Holder Slim',3,'card+holder','Ví đựng thẻ siêu mỏng nhẹ'],
    ['Balo Du Lịch 30L',3,'travel+backpack','Balo du lịch 30 lít chống nước'],
    ['Túi Gym Duffel Bag',3,'gym+duffel+bag','Túi gym duffel chống thấm'],
    ['Kính Mát Aviator Classic',3,'aviator+sunglasses','Kính mát aviator phong cách phi công'],
    ['Khăn Bandana Cotton',3,'bandana+cotton','Khăn bandana cotton đa phong cách'],
    ['Túi Shopper Tote Lớn',3,'shopper+tote','Túi shopper tote rộng rãi tiện dụng'],
    ['Mũ Fedora Vintage',3,'fedora+hat','Mũ fedora vintage phong cách nghệ sĩ'],
    ['Vớ Thể Thao Ankle',3,'ankle+socks+sport','Vớ thể thao cổ ngắn chống trượt'],
    ['Túi Belt Bag Sport',3,'belt+bag+sport','Túi đeo hông thể thao tiện lợi'],
    ['Balo Sling Bag Urban',3,'sling+bag','Balo sling bag phong cách urban'],
    ['Túi Laptop Sleeve 15in',3,'laptop+sleeve','Túi đựng laptop 15 inch chống sốc'],
    ['Mũ Trucker Mesh Cool',3,'trucker+cap','Mũ trucker lưới thoáng mát'],
    ['Khăn Scarf Cashmere',3,'cashmere+scarf','Khăn quàng cashmere mềm mại'],
    ['Ví Bifold Da Thật',3,'bifold+wallet','Ví gập đôi da thật cao cấp'],
];

$row = 2;
$simpleCount = 0;
$variantCount = 0;

foreach ($products as $i => $p) {
    [$name, $catId, $keyword, $shortDesc] = $p;
    $isVariant = ($i >= 50); // 50 first = simple, 50 last = variant
    $slug = strtolower(str_replace([' ','+'], ['-','-'], $keyword));
    $mainImg = "https://loremflickr.com/800/800/{$keyword}?lock=" . ($i + 100);

    if (!$isVariant) {
        // === SIMPLE ===
        $price = rand(89,1200)*1000;
        $compare = round($price*1.25, -3);
        $sheet->setCellValue("A{$row}", $name);
        $sheet->setCellValue("B{$row}", 'simple');
        $sheet->setCellValue("C{$row}", $catId);
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $shortDesc);
        $sheet->setCellValue("F{$row}", "<p>{$shortDesc}. Cam kết chính hãng, chất lượng cao.</p>");
        $sheet->setCellValue("G{$row}", 'active');
        $sheet->setCellValue("H{$row}", rand(0,3)===0?1:0);
        $sheet->setCellValue("I{$row}", $mainImg);
        $sheet->setCellValue("J{$row}", '');
        $sheet->setCellValue("K{$row}", '');
        $sheet->setCellValue("L{$row}", '');
        $sheet->setCellValue("M{$row}", $price);
        $sheet->setCellValue("N{$row}", $compare);
        $sheet->setCellValue("O{$row}", rand(10,150));
        $sheet->setCellValue("P{$row}", '');
        if ($simpleCount%2===0) $sheet->getStyle("A{$row}:P{$row}")->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF3E0']]]);
        $row++;
        $simpleCount++;
    } else {
        // === VARIANT ===
        $basePrice = rand(150,1200)*1000;
        $featured = rand(0,3)===0?1:0;
        shuffle($allColors);
        $vColors = array_slice($allColors, 0, rand(2,3));
        $vSizes = ($catId==3) ? ['Free Size'] : array_slice($sizes, 0, rand(2,3));

        $vars = [];
        foreach ($vColors as $vc) {
            foreach ($vSizes as $vs) {
                $vars[] = [$vc, $vs];
                if (count($vars) >= rand(2,4)) break 2;
            }
        }

        $first = true;
        $startRow = $row;
        foreach ($vars as $v) {
            [$vColor, $vSize] = $v;
            $hex = $colorHex[$vColor] ?? 'CCCCCC';
            $textHex = in_array($vColor, ['Trắng','Be','Kem','Vàng']) ? '333333' : 'FFFFFF';
            $varImg = "https://placehold.co/600x600/{$hex}/{$textHex}?text=" . urlencode($vColor);
            $vp = $basePrice + rand(-2,3)*10000;
            if ($vp<50000) $vp=$basePrice;
            $vc2 = round($vp*1.3, -3);

            $sheet->setCellValue("A{$row}", $name);
            if ($first) {
                $sheet->setCellValue("B{$row}", 'variant');
                $sheet->setCellValue("C{$row}", $catId);
                $sheet->setCellValue("D{$row}", '');
                $sheet->setCellValue("E{$row}", $shortDesc);
                $sheet->setCellValue("F{$row}", "<p>{$shortDesc}. Nhiều màu sắc và size lựa chọn.</p>");
                $sheet->setCellValue("G{$row}", 'active');
                $sheet->setCellValue("H{$row}", $featured);
                $sheet->setCellValue("I{$row}", $mainImg);
                $sheet->setCellValue("J{$row}", '');
                $first = false;
            } else {
                for ($c=1;$c<=9;$c++) $sheet->setCellValue(chr(65+$c).$row, '');
            }
            $sheet->setCellValue("K{$row}", $vColor);
            $sheet->setCellValue("L{$row}", $vSize);
            $sheet->setCellValue("M{$row}", $vp);
            $sheet->setCellValue("N{$row}", $vc2);
            $sheet->setCellValue("O{$row}", rand(5,60));
            $sheet->setCellValue("P{$row}", $varImg);
            $row++;
        }
        $bg = ($variantCount%2===0) ? 'E3F2FD' : 'E8F5E9';
        $sheet->getStyle("A{$startRow}:P".($row-1))->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]]]);
        $variantCount++;
    }
}

$widths = ['A'=>32,'B'=>12,'C'=>14,'D'=>16,'E'=>35,'F'=>40,'G'=>12,'H'=>8,'I'=>45,'J'=>15,'K'=>14,'L'=>12,'M'=>14,'N'=>14,'O'=>14,'P'=>45];
foreach ($widths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

$out = __DIR__ . '/100_san_pham_co_hinh.xlsx';
(new Xlsx($spreadsheet))->save($out);
echo "✅ File: {$out}\n";
echo "📊 100 SP: {$simpleCount} simple + {$variantCount} variant\n";
echo "📝 Tổng dòng: " . ($row-2) . "\n";
echo "🖼️ Ảnh chính: loremflickr (theo keyword sản phẩm)\n";
echo "🎨 Ảnh biến thể: placehold.co (đúng màu sản phẩm)\n";
