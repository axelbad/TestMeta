# Image Resizer Application

This project is a PHP application that handles configuration and image resizing. It uses Docker for containerization and PHPUnit for testing.

### Prerequisites

- Docker
- Docker Compose

### Installation

1. Clone the repository:
    ```sh
    git clone https://github.com/axelbad/TestMeta.git
    cd testMeta
    ```

2. Build and start the Docker containers:
    ```sh
    docker-compose up --build
    ```

3. Access the application in your browser at `http://localhost`.

### Usage

The main entry point of the application is [index.php]. It initializes the configuration and resizes an image.

### Configuration

The configuration is handled by the `handleConfig` class, which loads values from an XML file. The XML configuration file is located at [myConfig.xml].

### Image Resizing

The `imageResize` class is used to resize images. It takes the configuration as a parameter and resizes the specified image.

## Running Tests

The project uses PHPUnit for testing. The tests are located in the [tests] directory.

1. To run the tests, first, ensure the Docker containers are running:
    ```sh
    docker-compose up -d
    ```

2. Execute the tests inside the Docker container:
    ```sh
    docker exec -it testmeta-app-1 bash
    ./vendor/bin/phpunit tests/HandleConfigTest.php 
    ```
