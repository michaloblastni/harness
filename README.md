# Build and run locally

```
./gradlew bootTestRun
```

# Build for Tomcat or other web server

```
./gradlew bootWar
```

# Build an executable jar

```
./gradlew bootJar
```

# Build a docker image

```
./gradlew bootBuildImage
```

# Generate the liquibase master changelog from the current DB

```
./gradlew generateChangelog
```

# Making the project run with Tomcat 10 from IntelliJ Ultimate

Assuming the project is in c:\projects\harness, edit catalina.bat of your c:\apps\apache-tomcat\

Add after :emptyClasspath

```
set "CLASSPATH=%CLASSPATH%%CATALINA_HOME%\bin\bootstrap.jar"

set "CLASSPATH=%CLASSPATH%;c:\Projects\harness\src\main\resources;c:\Projects\harness\src\main\resources\templates;c:\Projects\harness;c:\Projects\harness\src\main"
cd c:\Projects\harness\
```

