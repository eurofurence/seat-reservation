import axios from 'axios'

// Shared helper for the "GET a file as a blob and trigger a browser download" pattern used
// by every CSV export / template download button across the admin pages.
export function useCsvDownload() {
    const download = async (url, filename) => {
        try {
            const response = await axios.get(url, { responseType: 'blob' })

            const objectUrl = window.URL.createObjectURL(new Blob([response.data]))
            const link = document.createElement('a')
            link.href = objectUrl
            link.setAttribute('download', filename)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(objectUrl)
        } catch (error) {
            console.error('Error downloading file:', error)
        }
    }

    return { download }
}
