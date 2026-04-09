<template>
  <div class="attendance-card">
    <h3>Quản lý ca làm việc</h3>
    <div v-if="!isCheckedIn">
      <button @click="handleCheckIn" :disabled="loading" class="btn-checkin">
        Check-in vào ca
      </button>
    </div>
    <div v-else>
      <p>Bắt đầu ca lúc: {{ checkInTime }}</p>
      <button @click="handleCheckOut" :disabled="loading" class="btn-checkout">
        Check-out kết thúc ca
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const isCheckedIn = ref(false);
const checkInTime = ref('');
const loading = ref(false);

const handleCheckIn = () => {
  loading.value = true;
  
  // Lấy vị trí GPS của nhân viên
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(async (position) => {
      try {
        const response = await axios.post('/api/attendance/check-in', {
          lat: position.coords.latitude,
          lng: position.coords.longitude
        });
        isCheckedIn.value = true;
        checkInTime.value = response.data.data.check_in_at;
      } catch (error) {
        alert(error.response.data.message);
      } finally {
        loading.value = false;
      }
    }, (error) => {
      alert("Bạn cần cấp quyền truy cập vị trí để check-in!");
      loading.value = false;
    });
  }
};
</script>