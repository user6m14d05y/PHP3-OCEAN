const axios = require('axios');

async function testGHN() {
    try {
        const response = await axios.get('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', {
            params: {
                "service_type_id": 2,
                "to_district_id": 2043, // Bac Lieu - Phuoc Long
                "to_ward_code": "620608",
                "weight": 3000,
            },
            headers: {
                Token: 'a715129f-37d0-11f1-84b6-de18d5436de6',
                ShopId: '5278356', // or whatever shop ID
            },
        });
        console.log("Success:", response.data);
    } catch (e) {
        console.log("Error:", e.response ? e.response.data : e.message);
    }
}
testGHN();
