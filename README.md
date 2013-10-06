# reference-parser

Parses references in academic papers and extracts their metadata such as
authors, title, date and so on.

# Usage

To use it you must first train on some hand-labeled data. Example of such
data is given in the file `training-data.txt`. To do training, execute
this command in a terminal:

```
$ php run-train.php > trained-data.php
```

This creates file `trained-data.php` with trained model parameters. Also,
if you want to see the actual transition and emission probability
matrices, you can print the HTML file with tables:

```
$ php run-train.php html > trained-data.html
```

To perform actual extraction process, execute:

```
$ php run-extract.php
```

This should print out labeled fields of a reference.

# Sample training data

`training-data.txt` contains some hand-labeled data with following fields:

```
<T> TITLE
<A> AUTHOR
<D> DATE
<P> PAGES
<V> VOLUME
<J> JOURNAL
<N> NUMBER
<U> URL
<B> PUBLISHER
<L> LOCATION
```

Of course you can create your own training data and capture the fields you
want. The quality of extraction greatly depends on volume of this data.
