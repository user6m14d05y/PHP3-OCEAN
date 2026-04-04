<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

/**
 * ProductsTemplateExport — Xuất file Excel mẫu để người dùng tải về điền dữ liệu
 *
 * === LOGIC ===
 * 1. Dòng heading (dòng 1): Tên các cột bằng tiếng Việt (có dấu) để dễ đọc
 *    → Hệ thống dùng dòng 2 (heading row kỹ thuật) để map: ten_san_pham, danh_muc_id, ...
 * 2. Dòng 2 trở đi: dữ liệu mẫu (2 dòng demo) để người dùng hiểu cách điền
 * 3. Style: heading đậm, có màu nền, cột có chiều rộng phù hợp
 */
class ProductsTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    /**
     * Heading kỹ thuật — chính là key mà ProductsImport sử dụng để đọc
     * Maatwebsite\Excel tự động convert heading xuống snake_case khi import
     */
    public function headings(): array
    {
        return [
            'TÊN SẢN PHẨM (*)',
            'MÃ DANH MỤC (*)',
            'MÃ THƯƠNG HIỆU',
            'GIÁ BÁN (*)',
            'GIÁ GỐC',
            'SỐ LƯỢNG KHO (*)',
            'MÔ TẢ NGẮN',
            'MÔ TẢ CHI TIẾT',
            'TRẠNG THÁI (active/draft)',
            'NỔI BẬT (1 hoặc 0)',
        ];
    }

    /**
     * Dữ liệu mẫu để người dùng tham khảo khi điền
     */
    public function array(): array
    {
        return [
            [
                'Áo Thun Cotton Nam Ocean',   // ten_san_pham
                1,                             // danh_muc_id (ví dụ ID=1)
                '',                            // thuong_hieu_id (để trống nếu không có)
                250000,                        // gia_ban
                350000,                        // gia_goc
                100,                           // so_luong_kho
                'Áo thun cotton cao cấp',      // mo_ta_ngan
                'Chất liệu cotton 100%...',    // mo_ta_chi_tiet
                'active',                      // trang_thai
                1,                             // noi_bat
            ],
            [
                'Quần Jeans Nữ Ocean Blue',
                2,
                '',
                450000,
                '',
                50,
                'Quần jeans phom rộng',
                '',
                'draft',
                0,
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Dòng tiêu đề chính (Row 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0288D1'], // Ocean Blue
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]
        ];
    }

    /**
     * Chiều rộng cột cho dễ đọc
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,  // ten_san_pham
            'B' => 15,  // danh_muc_id
            'C' => 15,  // thuong_hieu_id
            'D' => 15,  // gia_ban
            'E' => 15,  // gia_goc
            'F' => 15,  // so_luong_kho
            'G' => 25,  // mo_ta_ngan
            'H' => 30,  // mo_ta_chi_tiet
            'I' => 15,  // trang_thai
            'J' => 10,  // noi_bat
        ];
    }
}
