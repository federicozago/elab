import {defineStore} from 'pinia'
import {ref,computed} from 'vue'

export const useFileStore = defineStore('fileStore',()=>{
  const selectedFile = ref(null)

  function setSelectedFile(file){
    selectedFile.value = file
  }

  function clearSelectedFile(){
    selectedFile.value = null
  }

  const hasFile = computed(() => !!selectedFile.value)

  return {
    selectedFile,
    hasFile,
    setSelectedFile,
    clearSelectedFile
  }

})
