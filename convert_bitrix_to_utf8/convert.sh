#!/bin/bash

file_pattern="*.php"

printf 'Start convert to encoding: UTF-8 , for all files: %s \n'  "$file_pattern"


# ищем фацлы
files=`find . -name "${file_pattern}"`

counter=0;
for file_name in ${files}
do
    ((counter=counter+1))

        ## вернет формат файла
        file_format=`file $file_name --mime-encoding | cut -d":" -f2 | sed -e 's/ //g'`


        #  каждую 1000 файлов выводит текущйи файл и счетчик обработанных файлов
        if [ $((counter % 1000)) -eq 0 ]; then
          echo 'process ' ${counter}
          echo $file_name
        fi

#        echo $file_name
#        echo $file_format

        if [ $file_format == 'iso-8859-1' ] || [ $file_format == 'unknown-8bit' ]; then

                file_tmp="${file_name}.tmp"

                #Rename the file to a temporary file
                mv $file_name $file_tmp

                iconv -f CP1251 -t UTF-8 $file_tmp > $file_name

                #Remove the temporary file
                rm $file_tmp

#                echo "File Name...: $file_name"
#                echo "From Format.: $file_format"
#                echo "To Format...: UTF-8"
#                echo "---------------------------------------------------"

        fi
done;

echo "Done!"