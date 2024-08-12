# Harness 0.0.1
This project allows targeted individuals to register and fill the messages (insults, threats, disinformation) they hear. As soon as 2 or more targeted individuals have filled they hear the same message, they become linked.
Linked victims can read each other's full list of messages they hear, and they can email each other.
This allows victims who hear the same repetitive messages to find each other.

These linked victims will then be able to talk with each other and discuss i.e. who they suspect is the agent doing it to all of them. They can find out if he talks like that, while they will each have the written messages. And they can use it as an empirical evidence against him. Together, linked victims of the same agent can report it incl. the insults they hear, and including any witnesses they find that the person they suspect talks like that.

This project will be deployed on some free hosting where targeted individuals can freely register and use it.

## Questions?
Ask at https://github.com/michaloblastni/harness/discussions/1
## Build and run locally

```
./gradlew bootTestRun
```

## Build for Tomcat or other web server

```
./gradlew bootWar
```

## Build an executable jar

```
./gradlew bootJar
```

## Build a docker image

```
./gradlew bootBuildImage
```

## Generate the liquibase master changelog from the current DB

```
./gradlew generateChangelog
```

## Making the project run with Tomcat 10 from IntelliJ Ultimate

Assuming the project is in c:\projects\harness, edit catalina.bat of your c:\apps\apache-tomcat\

Add after :emptyClasspath

```
set "CLASSPATH=%CLASSPATH%%CATALINA_HOME%\bin\bootstrap.jar"

set "CLASSPATH=%CLASSPATH%;c:\Projects\harness\src\main\resources;c:\Projects\harness\src\main\resources\templates;c:\Projects\harness;c:\Projects\harness\src\main"
cd c:\Projects\harness\
```

