<script setup>
import { ref, onMounted } from 'vue';
import vueFilePond from 'vue-filepond';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css';

import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageCrop from 'filepond-plugin-image-crop';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginImageTransform from 'filepond-plugin-image-transform';
import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';

const FilePond = vueFilePond(
    FilePondPluginFileValidateType,
    FilePondPluginImagePreview,
    FilePondPluginImageExifOrientation,
    FilePondPluginImageCrop,
    FilePondPluginImageResize,
    FilePondPluginImageTransform
);

const props = defineProps({
    modelValue: {
        type: [String, Object],
        default: null,
    },
    acceptedFileTypes: {
        type: Array,
        default: () => ['image/jpeg', 'image/png', 'application/pdf'],
    },
    existingFileUrl: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        default: 'Drag & Drop your receipt or <span class="filepond--label-action">Browse</span>',
    },
});

const emit = defineEmits(['update:modelValue']);

const pond = ref(null);
const files = ref([]);

onMounted(() => {
    if (props.existingFileUrl) {
        files.value = [
            {
                source: props.existingFileUrl,
                options: {
                    type: 'local',
                },
            },
        ];
    }
});

const handleFileProcess = (error, file) => {
    if (!error) {
        emit('update:modelValue', file.serverId);
    }
};

const handleFileRemove = () => {
    emit('update:modelValue', null);
};

const serverOptions = {
    url: '',
    process: {
        url: '/uploads/tmp',
        method: 'POST',
        onload: (response) => response,
    },
    revert: '/uploads/tmp',
    load: (source, load, error, progress, abort, headers) => {
        var myRequest = new Request(source);
        fetch(myRequest).then(function(response) {
            response.blob().then(function(myBlob) {
                load(myBlob);
            });
        });
    },
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    },
};
</script>

<template>
    <div class="file-uploader">
        <file-pond
            ref="pond"
            name="receipt"
            :label-idle="label"
            :allow-multiple="false"
            :accepted-file-types="acceptedFileTypes"
            :server="serverOptions"
            :files="files"
            image-crop-aspect-ratio="1:1"
            image-resize-target-width="800"
            image-resize-target-height="800"
            @processfile="handleFileProcess"
            @removefile="handleFileRemove"
        />
    </div>
</template>

<style>
.filepond--wrapper {
    @apply cursor-pointer;
}
.filepond--panel-root {
    @apply bg-gray-100 dark:bg-gray-800;
}
.filepond--drop-label {
    @apply text-gray-600 dark:text-gray-300;
}
</style>
