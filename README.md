# Omeka S Learning Object Module

This Omeka S module is designed to facilitate the upload and management of learning objects, primarily in SCORM format (as zip packages), as media items for Omeka S items.

## Features

- **Learning Object Ingester**: Automatically recognizes valid learning object packages (e.g., SCORM zip files) during media upload.
- **Package Extraction**: Unzips the learning object package into a designated directory on the server and stores its location for later access.
- **Learning Object Renderer**: Provides a media renderer that displays a predefined thumbnail image for learning objects.
- **Interactive Viewing**: Offers two icons on the thumbnail:
    - **Download**: Allows users to download the original learning object package.
    - **Visualize**: Enables visualization of the learning object directly within the web browser, either embedded in an iframe on the same page or opened in a new browser tab.

## Installation

1.  **Download the Module**: Download the latest release of the module from the [GitHub releases page](https://github.com/ateeducacion/OmekaS-LearningObject/releases).
2.  **Unzip and Upload**: Unzip the downloaded package and upload the `LearningObject` directory to the `modules` directory of your Omeka S installation.
3.  **Enable the Module**:
    *   Log in to your Omeka S administration panel.
    *   Navigate to "Modules" in the left sidebar.
    *   Locate "Learning Object" in the list of available modules and click the "Install" button.
4.  **Configure the Module**: After installation, you may need to configure the module settings under "Modules" -> "Learning Object" -> "Configure" to activate the module.
