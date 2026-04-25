import { ref } from "vue";

const STORAGE_KEY = "namain_privacy_mode";

const isPrivate = ref(localStorage.getItem(STORAGE_KEY) === "1");

const togglePrivacy = () => {
    isPrivate.value = !isPrivate.value;
    localStorage.setItem(STORAGE_KEY, isPrivate.value ? "1" : "0");
};

export const usePrivacyMode = () => ({ isPrivate, togglePrivacy });
