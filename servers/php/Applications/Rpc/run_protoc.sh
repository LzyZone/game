#!/bin/bash

files=`find ./Protobuf -name '*.proto'`

for file in $files
do
echo $file
protoc --proto_path=./Protobuf --php_out=./ --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin $file
done
