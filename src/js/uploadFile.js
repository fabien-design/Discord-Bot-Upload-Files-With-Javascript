// Define the chunk size
const CHUNK_SIZE = 10 * 1024 * 1024; // 5 MB

// Get the file input
const fileInput = document.querySelector("#file");

// Handle the file change event
fileInput.addEventListener("change", async (event) => {


    // Get the file
    const file = event.target.files[0];
    const file_parts = [];
    // Split the file into chunks
    const chunks = [];
    let start = 0;

    document.querySelector("#modalProgressBar").style.display = "block";

    while (start < file.size) {
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);
        chunks.push(chunk);
        start = end;
    }

    const calculProgressBar = (100 / chunks.length).toFixed(2);
    let cnt = 0;
    // Upload the chunks
    for (let i = 0; i < chunks.length; i++) {
        const chunk = chunks[i];

        try {
            // Create FormData
            const formData = new FormData();
            formData.append("action", "upload");
            formData.append("filename", file.name + "_part" + i);
            formData.append("chunk", chunk);

            // Create the request
            const response = await axios.post("upload.php", formData);

            // The chunk was uploaded successfully
            console.log(`Chunk ${i + 1} uploaded successfully.`);
            // Store the part of the filename in an array
            file_parts.push(file.name + "_part" + i);
            let bar = document.querySelector("#progressBar");
             
            document.querySelector("#progressBarText").innerHTML = `<p>${
              calculProgressBar * (i + 1)
            }%</p>`;
            cnt = calculProgressBar * (i + 1);
            bar.style.width = cnt + "%";
        } catch (error) {
            // An error occurred
            console.log(`Error uploading chunk ${i + 1}:`, error);
        }
    }
    const formData = new FormData();
    formData.append("action", "sendToBot");
    formData.append("filename", file.name);
    formData.append("file_parts", JSON.stringify(file_parts));
    formData.append("userId", file_parts);
    const response = await axios.post("upload.php", formData);
    if (response.data.success === true) {
        document.querySelector("#progressBarText").innerHTML = `<p>
        Le téléchargement et l'envoi du fichier sur Discord a été effectué</p>`;
    } else {
         document.querySelector("#progressBarText").innerHTML = `<p>
        Le téléchargement et l'envoi du fichier sur Discord a échoué</p>`;
        console.log(response.data);
    }
    setTimeout(() => {
        document.querySelector("#modalProgressBar").style.display = "none";
        document.querySelector("#progressBar").style.width = 0;
        fileInput.value = null;
    }, 2000);
});
